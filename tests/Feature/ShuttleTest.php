<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\Terminal;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletTransfer;
use App\Services\ShuttleService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShuttleTest extends TestCase
{
    use RefreshDatabase;

    private ShuttleService $service;

    private User $student;

    private User $driver;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->service = app(ShuttleService::class);
        $this->student = User::where('email', 'student@ful.test')->firstOrFail();
        $this->driver = User::where('email', 'driver18@ful.test')->firstOrFail();
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function runFor(?User $driver = null): Run
    {
        $driver ??= $this->driver;
        $bus = $driver->buses()->firstOrFail();
        $fare = Fare::where('bus_type', $bus->type)->firstOrFail();

        return $this->service->openRun($driver, $bus->id, $fare->id, now()->addHour()->toDateTimeString());
    }

    private function book(Run $run, ?User $student = null, ?string $key = null): Booking
    {
        return $this->service->book($student ?? $this->student, $run->id, $key ?? (string) Str::uuid());
    }

    private function rejects(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a validation error: '.$message);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, implode(' ', array_merge(...array_values($e->errors()))));
        }
    }

    public function test_documented_demo_balances_and_seats(): void
    {
        $run = $this->runFor();
        $this->assertSame(18, $run->seats_left);
        $booking = $this->book($run);
        $this->assertSame(17, $run->fresh()->seats_left);
        $this->assertSame(80000, $this->student->wallet->fresh()->balance_kobo);
        $this->assertSame(20000, $this->driver->wallet->fresh()->balance_kobo);
        $this->service->updateRun($this->driver, $run->id, 3, null);
        $this->assertSame(14, $run->fresh()->seats_left);
        $this->service->cancelBooking($this->student, $booking->id);
        $this->assertSame(15, $run->fresh()->seats_left);
        $this->assertSame(100000, $this->student->wallet->fresh()->balance_kobo);
        $this->assertSame(0, $this->driver->wallet->fresh()->balance_kobo);
        $this->assertSame(2, WalletTransfer::whereIn('kind', ['booking', 'refund'])->count());
        $this->assertSame(4, WalletEntry::whereIn('transfer_id', WalletTransfer::whereIn('kind', ['booking', 'refund'])->select('id'))->count());
    }

    public function test_booking_retry_returns_same_ticket_even_after_departure(): void
    {
        $run = $this->runFor();
        $key = (string) Str::uuid();
        $first = $this->book($run, key: $key);
        $this->assertSame($first->id, $this->book($run, key: $key)->id);
        $this->service->transition($this->driver, $run->id, 'departed');
        $this->assertSame($first->id, $this->book($run, key: $key)->id);
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, WalletTransfer::where('kind', 'booking')->count());
        $this->assertSame(80000, $this->student->wallet->fresh()->balance_kobo);
    }

    public function test_insufficient_credit_rolls_back_all_changes(): void
    {
        $run = $this->runFor();
        $this->student->wallet->update(['balance_kobo' => 19999]);
        $count = WalletEntry::count();
        $this->rejects(fn () => $this->book($run), 'Insufficient wallet');
        $this->assertSame(0, Booking::count());
        $this->assertSame($count, WalletEntry::count());
        $this->assertSame(19999, $this->student->wallet->fresh()->balance_kobo);
        $this->assertSame(0, $this->driver->wallet->fresh()->balance_kobo);
    }

    public function test_full_bus_rejects_booking_without_payment(): void
    {
        $run = $this->runFor();
        $this->service->updateRun($this->driver, $run->id, 18, null);
        $this->rejects(fn () => $this->book($run), 'full');
        $this->assertSame(0, Booking::count());
        $this->assertSame(100000, $this->student->wallet->fresh()->balance_kobo);
    }

    public function test_walk_ins_and_check_in_do_not_double_count_online_passengers(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->service->board($this->driver, $booking->id);
        $this->service->board($this->driver, $booking->id);
        $this->assertSame(17, $run->fresh()->seats_left);
        $this->rejects(fn () => $this->service->updateRun($this->driver, $run->id, 18, null), 'exceeds');
        $this->assertSame(0, $run->fresh()->walk_in_count);
        $this->service->updateRun($this->driver, $run->id, 17, null);
        $this->assertSame(0, $run->fresh()->seats_left);
        $this->assertSame(1, WalletTransfer::where('kind', 'booking')->count());
    }

    public function test_student_cannot_hold_two_boarding_bookings_across_runs(): void
    {
        $first = $this->runFor();
        $otherDriver = User::where('email', 'driver12@ful.test')->firstOrFail();
        $second = $this->runFor($otherDriver);
        $this->book($first);
        $this->rejects(fn () => $this->book($second), 'already have a booking');
        $this->assertSame(1, Booking::count());
        $this->service->transition($this->driver, $first->id, 'departed');
        $this->book($second);
        $this->assertSame(2, Booking::count());
    }

    public function test_cancellation_is_idempotent_and_rebooking_is_allowed(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->service->cancelBooking($this->student, $booking->id);
        $this->service->cancelBooking($this->student, $booking->id);
        $this->assertSame(1, WalletTransfer::where('kind', 'refund')->count());
        $refund = WalletTransfer::where('kind', 'refund')->firstOrFail();
        $this->assertSame($booking->payment_transfer_id, $refund->original_transfer_id);
        $this->assertSame(18, $run->fresh()->seats_left);
        $newBooking = $this->book($run);
        $this->assertNotSame($booking->id, $newBooking->id);
        $this->assertSame(17, $run->fresh()->seats_left);
    }

    public function test_boarded_ticket_cannot_be_self_cancelled(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->service->board($this->driver, $booking->id);
        $this->rejects(fn () => $this->service->cancelBooking($this->student, $booking->id), 'checked in');
        $this->assertSame(0, WalletTransfer::where('kind', 'refund')->count());
    }

    public function test_cancel_run_refunds_booked_and_boarded_tickets_only_once(): void
    {
        $run = $this->runFor();
        $first = $this->book($run);
        $secondStudent = User::where('email', 'student2@ful.test')->firstOrFail();
        $second = $this->book($run, $secondStudent);
        $this->service->board($this->driver, $second->id);
        $this->service->transition($this->admin, $run->id, 'cancelled');
        $this->service->transition($this->driver, $run->id, 'cancelled');
        $this->service->cancelBooking($this->student, $first->id);
        $this->assertSame(2, WalletTransfer::where('kind', 'refund')->count());
        $this->assertSame(2, Booking::where('status', 'cancelled')->count());
        $this->assertSame(0, $this->driver->wallet->fresh()->balance_kobo);
        $this->assertSame(100000, $secondStudent->wallet->fresh()->balance_kobo);
        $this->assertSame(100000, $this->student->wallet->fresh()->balance_kobo);
    }

    public function test_previously_refunded_booking_is_not_refunded_again_on_run_cancellation(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->service->cancelBooking($this->student, $booking->id);
        $this->service->transition($this->driver, $run->id, 'cancelled');
        $this->assertSame(1, WalletTransfer::where('kind', 'refund')->count());
    }

    public function test_departure_closes_booking_refunds_check_in_and_updates_but_preserves_ticket(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->service->transition($this->driver, $run->id, 'departed');
        $this->rejects(fn () => $this->book($run), 'no longer boarding');
        $this->rejects(fn () => $this->service->cancelBooking($this->student, $booking->id), 'closed');
        $this->rejects(fn () => $this->service->board($this->driver, $booking->id), 'closed');
        $this->rejects(fn () => $this->service->updateRun($this->driver, $run->id, 1, null), 'Only a boarding');
        $this->rejects(fn () => $this->service->transition($this->driver, $run->id, 'cancelled'), 'cannot change');
        $this->actingAs($this->student)->get(route('tickets.show', $booking))->assertOk()->assertSee($booking->booking_code);
        $this->get('/?fragment=1')->assertDontSee($run->bus->identifier);
        $this->service->transition($this->driver, $run->id, 'arrived');
        $this->assertSame('arrived', $run->fresh()->status);
        $this->runFor();
        $this->assertSame(2, Run::count());
    }

    public function test_active_driver_and_bus_cannot_open_a_second_run(): void
    {
        $this->runFor();
        $this->rejects(fn () => $this->runFor(), 'already have an active run');
        $this->assertSame(1, Run::count());
    }

    public function test_unapproved_bus_and_inactive_fare_or_terminal_cannot_open_runs(): void
    {
        $bus = $this->driver->buses()->firstOrFail();
        $bus->update(['approval_status' => 'pending']);
        $this->rejects(fn () => $this->runFor(), 'must be approved');
        $bus->update(['approval_status' => 'approved']);
        $fare = Fare::where('bus_type', $bus->type)->firstOrFail();
        $fare->update(['active' => false]);
        $this->rejects(fn () => $this->runFor(), 'active fare');
        $fare->update(['active' => true]);
        $fare->origin->update(['active' => false]);
        $this->rejects(fn () => $this->runFor(), 'terminals must be active');
    }

    public function test_fare_and_capacity_are_snapshotted_when_run_opens(): void
    {
        $run = $this->runFor();
        $run->bus->update(['capacity' => 60]);
        Fare::where('bus_type', 'Minibus')->update(['amount_kobo' => 50000]);
        $booking = $this->book($run);
        $this->assertSame(18, $run->fresh()->capacity_snapshot);
        $this->assertSame(20000, $booking->fare_kobo);
    }

    public function test_credit_retry_keeps_one_credit_with_admin_and_reason(): void
    {
        $key = (string) Str::uuid();
        $first = $this->service->credit($this->admin, $this->student, 50000, 'Assessment credit', $key);
        $again = $this->service->credit($this->admin, $this->student, 50000, 'Assessment credit', $key);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(150000, $this->student->wallet->fresh()->balance_kobo);
        $this->assertSame($this->admin->id, $first->entries()->first()->actor_id);
        $this->rejects(fn () => $this->service->credit($this->admin, $this->student, 60000, 'Assessment credit', $key), 'different credit');
        $this->rejects(fn () => $this->service->credit($this->admin, $this->student, 0, 'Assessment credit', 'other'), 'positive');
    }

    public function test_estimate_updates_and_overdue_label_are_visible_after_refresh(): void
    {
        $run = $this->runFor();
        $estimate = now()->addHours(2)->startOfMinute();
        $this->service->updateRun($this->driver, $run->id, null, $estimate->toDateTimeString());
        $this->get('/?fragment=1')->assertOk()->assertSee($estimate->format('g:i A'));
        $this->travel(3)->hours();
        $this->get('/?fragment=1')->assertSee('Departure time pending update');
        $this->actingAs($this->driver)->get(route('driver.runs.show', $run))->assertSee('departure estimate has passed');
    }

    public function test_roles_and_ownership_protect_mutations_and_private_tickets(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->actingAs($this->student)->get('/admin')->assertForbidden();
        $this->post(route('admin.students.credit', $this->student), [])->assertForbidden();
        $this->patch(route('driver.runs.update', $run), [])->assertForbidden();
        $other = User::where('email', 'driver12@ful.test')->firstOrFail();
        $this->actingAs($other)->get(route('driver.runs.show', $run))->assertForbidden();
        $this->patch(route('driver.runs.update', $run), ['walk_in_count' => 1])->assertForbidden();
        $this->post(route('driver.bookings.board', $booking))->assertForbidden();
        $otherStudent = User::where('email', 'student2@ful.test')->firstOrFail();
        $this->actingAs($otherStudent)->get(route('tickets.show', $booking))->assertForbidden();
        $this->post(route('tickets.cancel', $booking))->assertForbidden();
        $this->assertSame('booked', $booking->fresh()->status);
    }

    public function test_registration_cannot_create_an_admin_and_creates_student_wallet(): void
    {
        $data = ['name' => 'New Student', 'email' => 'new@ful.test', 'password' => 'Campus@2026', 'password_confirmation' => 'Campus@2026', 'student_identifier' => 'FUL/NEW/1'];
        $this->post('/register', $data + ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'new@ful.test']);
        $this->post('/register', $data + ['role' => 'student'])->assertRedirect(route('dashboard'));
        $user = User::where('email', 'new@ful.test')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertSame(0, $user->wallet->balance_kobo);
        $this->assertAuthenticatedAs($user);
    }

    public function test_booking_http_requires_fare_confirmation_and_valid_idempotency_key(): void
    {
        $run = $this->runFor();
        $this->actingAs($this->student)->post(route('book', $run), ['operation_key' => 'bad'])->assertSessionHasErrors(['operation_key', 'confirmed']);
        $this->post(route('book', $run), ['operation_key' => (string) Str::uuid(), 'confirmed' => '1'])->assertRedirect();
        $this->assertSame(1, Booking::count());
    }

    public function test_admin_settings_and_approval_flow(): void
    {
        $this->actingAs($this->driver)->post(route('driver.buses.store'), ['identifier' => 'NEW-001', 'type' => 'Van', 'capacity' => 10, 'ownership_details' => 'Driver owned'])->assertRedirect();
        $bus = Bus::where('identifier', 'NEW-001')->firstOrFail();
        $this->assertSame('pending', $bus->approval_status);
        $this->actingAs($this->admin)->patch(route('admin.buses.approve', $bus), ['approval_status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $bus->fresh()->approval_status);
        $terminal = Terminal::first();
        $this->post(route('admin.terminals.save', $terminal), ['name' => 'Main Loading Point', 'active' => 1])->assertRedirect();
        $this->assertSame('Main Loading Point', $terminal->fresh()->name);
        $fare = Fare::first();
        $this->post(route('admin.fares.save'), ['origin_id' => $fare->origin_id, 'destination_id' => $fare->destination_id, 'bus_type' => $fare->bus_type, 'amount' => '250.50', 'active' => 1])->assertRedirect();
        $this->assertSame(25050, $fare->fresh()->amount_kobo);
        $this->post(route('admin.fares.save'), ['origin_id' => $fare->origin_id, 'destination_id' => $fare->origin_id, 'bus_type' => $fare->bus_type, 'amount' => '0.009', 'active' => 1])->assertSessionHasErrors();
    }

    public function test_all_pages_render_and_reports_match_ledger(): void
    {
        $run = $this->runFor();
        $booking = $this->book($run);
        $this->get('/')->assertOk();
        $this->get(route('runs.show', $run))->assertOk();
        $this->get(route('runs.availability', $run))->assertJsonPath('seats_left', 17);
        foreach (['admin.index', 'admin.buses', 'admin.settings', 'admin.students', 'admin.reports'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
        $this->actingAs($this->driver)->get(route('driver.index'))->assertOk();
        $this->get(route('driver.runs.show', $run))->assertOk();
        $this->get(route('wallet'))->assertOk()->assertSee($booking->booking_code);
        $this->actingAs($this->student)->get(route('bookings'))->assertOk();
        $this->get(route('wallet'))->assertOk();
        $this->service->cancelBooking($this->student, $booking->id);
        $this->actingAs($this->admin)->get(route('admin.reports'))->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals['bookings'] === 20000 && (int) $totals['refunds'] === 20000 && (int) $totals['credits'] === 200000);
    }

    public function test_decimal_currency_is_exact_and_never_rounded_silently(): void
    {
        $this->assertSame(20001, Money::parse('200.01'));
        $this->assertSame(20010, Money::parse('200.1'));
        $this->assertSame('₦200.01', Money::format(20001));
        $this->assertSame('−₦200.01', Money::format(-20001));
        $this->rejects(fn () => Money::parse('200.001'), 'two decimal');
    }
}
