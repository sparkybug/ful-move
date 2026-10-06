<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\Terminal;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\ShuttleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBoardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_boarding_requires_demo_mode(): void
    {
        app()->instance('env', 'production');
        config(['app.allow_demo_seed' => false]);

        $this->artisan('demo:boarding')->assertExitCode(1);

        $this->assertDatabaseCount('runs', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_demo_startup_is_repeatable_and_preserves_bookings(): void
    {
        app()->instance('env', 'production');
        config(['app.allow_demo_seed' => true]);
        $this->withoutVite();

        $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);
        $this->artisan('demo:boarding')->assertExitCode(0);
        $this->assertDatabaseCount('runs', 4);
        $this->get('/')->assertOk()->assertSee('FUL-018')->assertSee('FUL-060')
            ->assertSee('FUL-012')->assertSee('FUL-010')->assertDontSee('No buses boarding just yet.');

        $student = User::where('email', 'student@ful.test')->firstOrFail();
        $run = Run::whereHas('bus', fn ($q) => $q->where('identifier', 'FUL-018'))->firstOrFail();
        $booking = app(ShuttleService::class)->book($student, $run->id, 'demo-redeploy-booking');
        $snapshot = Run::orderBy('id')->get()->toArray();
        $password = $student->password;

        $this->travel(5)->minutes();
        $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);
        $this->artisan('demo:boarding')->assertExitCode(0);

        $this->assertSame($snapshot, Run::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('buses', 4);
        $this->assertDatabaseCount('fares', 8);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame('booked', $booking->fresh()->status);
        $this->assertSame(80000, $student->wallet->fresh()->balance_kobo);
        $this->assertSame(2, WalletTransfer::where('kind', 'credit')->count());
        $this->assertSame($password, $student->fresh()->password);
    }

    public function test_automatic_boarding_respects_driver_and_admin_changes_and_ignores_other_buses(): void
    {
        $this->seed();
        $driver = User::where('email', 'driver18@ful.test')->firstOrFail();
        $run = app(ShuttleService::class)->openRun($driver, $driver->buses()->first()->id,
            Fare::where('bus_type', 'Minibus')->first()->id, now()->addHour()->toDateTimeString());
        app(ShuttleService::class)->transition($driver, $run->id, 'departed');
        Bus::where('identifier', 'FUL-010')->update(['approval_status' => 'pending']);
        Fare::where('bus_type', 'Coach')->update(['active' => false]);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $otherBus = $otherDriver->buses()->create([
            'identifier' => 'CAMPUS-OTHER', 'type' => 'Minibus', 'capacity' => 18,
            'ownership_details' => 'Another driver', 'approval_status' => 'approved',
        ]);

        $this->seed();
        $this->artisan('demo:boarding')->assertExitCode(0);

        $this->assertDatabaseCount('runs', 2);
        $this->assertSame('departed', $run->fresh()->status);
        $this->assertSame('FUL-012', Run::where('status', 'boarding')->firstOrFail()->bus->identifier);
        $this->assertSame(0, $otherBus->runs()->count());
        $this->assertSame('pending', Bus::where('identifier', 'FUL-010')->firstOrFail()->approval_status);
        $this->assertSame(0, Fare::where('bus_type', 'Coach')->where('active', true)->count());
    }

    public function test_inactive_terminals_are_not_reenabled_or_used_for_automatic_boarding(): void
    {
        $this->seed();
        Terminal::query()->update(['active' => false]);

        $this->seed();
        $this->artisan('demo:boarding')->assertExitCode(0);

        $this->assertDatabaseCount('runs', 0);
        $this->assertSame(0, Terminal::where('active', true)->count());
    }
}
