<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShuttleService
{
    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['shuttle' => $message]);
        }
    }

    // Every seat writer locks the run; wallets are always locked in ascending ID order.
    private function wallets(array $userIds): Collection
    {
        $wallets = Wallet::whereIn('user_id', array_unique($userIds))->orderBy('id')->lockForUpdate()->get()->keyBy('user_id');
        $this->require($wallets->count() === count(array_unique($userIds)), 'A required wallet is missing. Contact an administrator.');

        return $wallets;
    }

    private function entry(Wallet $wallet, WalletTransfer $transfer, string $type): void
    {
        $balance = $wallet->balance_kobo + ($type === 'credit' ? $transfer->amount_kobo : -$transfer->amount_kobo);
        $this->require($balance >= 0, 'Insufficient wallet balance for this transaction.');
        $wallet->update(['balance_kobo' => $balance]);
        $wallet->entries()->create([
            'transfer_id' => $transfer->id, 'type' => $type,
            'amount_kobo' => $transfer->amount_kobo, 'balance_after_kobo' => $balance,
            'actor_id' => $transfer->actor_id, 'reason' => $transfer->reason,
        ]);
    }

    public function credit(User $admin, User $student, int $amount, string $reason, string $key): WalletTransfer
    {
        abort_unless($admin->role === 'admin' && $student->role === 'student', 403);
        $this->require($amount > 0 && $amount <= 999999999, 'Enter a positive credit amount up to ₦9,999,999.99.');

        return DB::transaction(function () use ($admin, $student, $amount, $reason, $key) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $wallets = $this->wallets([$student->id]);
            $operation = 'credit:'.$admin->id.':'.$key;
            if ($existing = WalletTransfer::where('operation_key', $operation)->first()) {
                $this->require($existing->recipient_id === $student->id && $existing->amount_kobo === $amount && $existing->reason === $reason, 'This request was already used for a different credit. Reload the form.');

                return $existing;
            }
            $transfer = WalletTransfer::create([
                'operation_key' => $operation, 'kind' => 'credit', 'recipient_id' => $student->id,
                'actor_id' => $admin->id, 'amount_kobo' => $amount, 'reason' => $reason,
            ]);
            $this->entry($wallets[$student->id], $transfer, 'credit');

            return $transfer;
        }, 5);
    }

    public function openRun(User $driver, int $busId, int $fareId, string $estimate): Run
    {
        abort_unless($driver->role === 'driver', 403);

        return DB::transaction(function () use ($driver, $busId, $fareId, $estimate) {
            User::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $bus = Bus::whereKey($busId)->lockForUpdate()->firstOrFail();
            abort_unless($bus->driver_id === $driver->id, 403);
            $this->require($bus->approval_status === 'approved', 'This bus must be approved before opening a run.');
            $this->require(! Run::whereIn('status', ['boarding', 'departed'])->where(fn ($q) => $q->where('driver_id', $driver->id)->orWhere('bus_id', $bus->id))->exists(), 'You or this bus already have an active run. Finish it first.');
            $fare = Fare::whereKey($fareId)->lockForUpdate()->firstOrFail();
            $this->require($fare->active && $fare->bus_type === $bus->type, 'Select an active fare for this bus type.');
            $this->require($fare->origin_id !== $fare->destination_id && Terminal::whereIn('id', [$fare->origin_id, $fare->destination_id])->where('active', true)->count() === 2, 'Both terminals must be active and different.');
            $date = Carbon::parse($estimate);
            $this->require($date->isFuture(), 'Choose a departure estimate in the future.');

            return Run::create([
                'bus_id' => $bus->id, 'driver_id' => $driver->id, 'origin_id' => $fare->origin_id,
                'destination_id' => $fare->destination_id, 'capacity_snapshot' => $bus->capacity,
                'fare_snapshot_kobo' => $fare->amount_kobo, 'estimated_departure_at' => $date,
                'estimate_updated_at' => now(), 'status' => 'boarding',
            ]);
        }, 5);
    }

    public function book(User $student, int $runId, string $key): Booking
    {
        abort_unless($student->role === 'student', 403);

        return DB::transaction(function () use ($student, $runId, $key) {
            // Serializes this student's bookings across different runs, not just a single bus.
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $run = Run::whereKey($runId)->lockForUpdate()->firstOrFail();
            $operation = 'booking:'.$student->id.':'.$key;
            if ($existing = WalletTransfer::where('operation_key', $operation)->first()) {
                $this->require($existing->booking->run_id === $run->id, 'This request was already used for another bus. Reload the page.');

                return $existing->booking;
            }
            $this->require($run->status === 'boarding', 'This bus is no longer boarding. Please choose another bus.');
            $this->require(! Booking::where('student_id', $student->id)->whereIn('status', ['booked', 'boarded'])->whereHas('run', fn ($q) => $q->where('status', 'boarding'))->exists(), 'You already have a booking on a boarding bus. View or cancel that ticket first.');
            $this->require($run->activeBookings()->count() + $run->walk_in_count < $run->capacity_snapshot, 'This bus is full. Please choose another bus.');
            $wallets = $this->wallets([$student->id, $run->driver_id]);
            $this->require($wallets[$student->id]->balance_kobo >= $run->fare_snapshot_kobo, 'Insufficient wallet balance. Ask an admin for transport credit.');
            $booking = Booking::create([
                'run_id' => $run->id, 'student_id' => $student->id, 'booking_code' => 'FUL-'.Str::upper(Str::random(10)),
                'fare_kobo' => $run->fare_snapshot_kobo, 'status' => 'booked',
            ]);
            $transfer = WalletTransfer::create([
                'operation_key' => $operation, 'kind' => 'booking', 'booking_id' => $booking->id,
                'recipient_id' => $run->driver_id, 'actor_id' => $student->id,
                'amount_kobo' => $booking->fare_kobo, 'reason' => 'Seat booking '.$booking->booking_code,
            ]);
            $this->entry($wallets[$student->id], $transfer, 'debit');
            $this->entry($wallets[$run->driver_id], $transfer, 'credit');
            $booking->update(['payment_transfer_id' => $transfer->id]);

            return $booking;
        }, 5);
    }

    private function refund(Booking $booking, Run $run, User $actor, Collection $wallets): void
    {
        if ($booking->status === 'cancelled') {
            return;
        }
        $transfer = WalletTransfer::create([
            'operation_key' => 'refund:'.$booking->payment_transfer_id, 'kind' => 'refund', 'booking_id' => $booking->id,
            'recipient_id' => $booking->student_id, 'actor_id' => $actor->id, 'amount_kobo' => $booking->fare_kobo,
            'original_transfer_id' => $booking->payment_transfer_id, 'reason' => 'Refund for '.$booking->booking_code,
        ]);
        $this->entry($wallets[$run->driver_id], $transfer, 'debit');
        $this->entry($wallets[$booking->student_id], $transfer, 'credit');
        $booking->update(['status' => 'cancelled']);
    }

    public function cancelBooking(User $student, int $bookingId): Booking
    {
        abort_unless($student->role === 'student', 403);
        $reference = Booking::findOrFail($bookingId);
        abort_unless($reference->student_id === $student->id, 403);

        return DB::transaction(function () use ($student, $reference) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $run = Run::whereKey($reference->run_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            if ($booking->status === 'cancelled') {
                return $booking;
            }
            $this->require($run->status === 'boarding', 'Cancellation is closed because this bus has left boarding.');
            $this->require($booking->status === 'booked', 'A ticket that has been checked in cannot be cancelled.');
            $wallets = $this->wallets([$student->id, $run->driver_id]);
            $this->refund($booking, $run, $student, $wallets);

            return $booking;
        }, 5);
    }

    private function ownsRun(User $actor, Run $run, bool $allowAdmin = false): void
    {
        abort_unless(($actor->role === 'driver' && $run->driver_id === $actor->id) || ($allowAdmin && $actor->role === 'admin'), 403);
    }

    public function updateRun(User $driver, int $id, ?int $walkIns, ?string $estimate): Run
    {
        return DB::transaction(function () use ($driver, $id, $walkIns, $estimate) {
            $run = Run::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->ownsRun($driver, $run);
            $this->require($run->status === 'boarding', 'Only a boarding run can be updated.');
            if ($walkIns !== null) {
                $this->require($walkIns >= 0 && $walkIns + $run->activeBookings()->count() <= $run->capacity_snapshot, 'That walk-in count exceeds the remaining capacity. Online bookings already hold their seats.');
                $run->walk_in_count = $walkIns;
            }
            if ($estimate !== null) {
                $date = Carbon::parse($estimate);
                $this->require($date->isFuture(), 'Choose a departure estimate in the future.');
                $run->estimated_departure_at = $date;
                $run->estimate_updated_at = now();
            }
            $run->save();

            return $run;
        }, 5);
    }

    public function board(User $driver, int $bookingId): void
    {
        $reference = Booking::findOrFail($bookingId);
        DB::transaction(function () use ($driver, $reference) {
            $run = Run::whereKey($reference->run_id)->lockForUpdate()->firstOrFail();
            $this->ownsRun($driver, $run);
            $booking = Booking::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            $this->require($run->status === 'boarding', 'Check-in is closed for this run.');
            $this->require($booking->status !== 'cancelled', 'A cancelled ticket cannot be checked in.');
            $booking->update(['status' => 'boarded']);
        }, 5);
    }

    public function transition(User $actor, int $id, string $status): Run
    {
        return DB::transaction(function () use ($actor, $id, $status) {
            $run = Run::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->ownsRun($actor, $run, $status === 'cancelled');
            if ($run->status === $status) {
                return $run;
            }
            $allowed = ['boarding' => ['departed', 'cancelled'], 'departed' => ['arrived']];
            $this->require(in_array($status, $allowed[$run->status] ?? [], true), 'This run cannot change from '.$run->status.' to '.$status.'.');
            if ($status === 'cancelled') {
                $bookings = $run->activeBookings()->orderBy('id')->lockForUpdate()->get();
                $wallets = $this->wallets([$run->driver_id, ...$bookings->pluck('student_id')->all()]);
                foreach ($bookings as $booking) {
                    $this->refund($booking, $run, $actor, $wallets);
                }
            }
            $run->update(['status' => $status]);

            return $run;
        }, 5);
    }
}
