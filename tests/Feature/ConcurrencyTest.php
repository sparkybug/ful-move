<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Fare;
use App\Models\Run;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\ShuttleService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function race(array $actions): array
    {
        $directory = storage_path('framework/testing/races/'.Str::uuid());
        mkdir($directory, 0777, true);
        $go = $directory.'/go';
        $processes = [];
        $connection = config('database.connections.mysql');
        foreach ($actions as $index => $action) {
            $action['go'] = $go;
            $action['ready'] = $directory.'/'.$index.'.ready';
            $process = new Process([PHP_BINARY, base_path('tests/support/race-worker.php'), json_encode($action)], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) $connection['host'],
                'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => (string) $connection['database'],
                'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
                'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            ]);
            $process->setTimeout(35);
            $process->start();
            $processes[] = $process;
        }
        try {
            $deadline = microtime(true) + 25;
            while (count(glob($directory.'/*.ready')) !== count($processes)) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the race barrier: '.implode(' ', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
                }
                usleep(10000);
            }
            touch($go);
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_real_database_races_preserve_seats_student_exclusivity_and_refunds(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run with phpunit.mysql.xml for real row-lock concurrency checks.');
        }
        $this->seed();
        $service = app(ShuttleService::class);
        $student = User::where('email', 'student@ful.test')->firstOrFail();
        $other = User::where('email', 'student2@ful.test')->firstOrFail();
        $driver = User::where('email', 'driver18@ful.test')->firstOrFail();
        $bus = $driver->buses()->firstOrFail();
        $fare = Fare::where('bus_type', $bus->type)->firstOrFail();
        $open = fn () => $service->openRun($driver, $bus->id, $fare->id, now()->addHour()->toDateTimeString());

        // Two independent PHP processes contend for the last seat.
        $run = $open();
        $service->updateRun($driver, $run->id, 17, null);
        $results = $this->race([
            ['action' => 'book', 'user' => $student->id, 'run' => $run->id, 'key' => (string) Str::uuid()],
            ['action' => 'book', 'user' => $other->id, 'run' => $run->id, 'key' => (string) Str::uuid()],
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(1, $run->activeBookings()->count());
        $this->assertSame(0, $run->fresh()->seats_left);
        $this->assertSame(1, WalletTransfer::where('kind', 'booking')->count());
        $this->assertSame(20000, $driver->wallet->fresh()->balance_kobo);

        // A repeated cancellation refunds the same booking exactly once.
        $results = $this->race([
            ['action' => 'cancel', 'user' => $driver->id, 'run' => $run->id],
            ['action' => 'cancel', 'user' => $driver->id, 'run' => $run->id],
        ]);
        $this->assertTrue($results[0]['ok'] && $results[1]['ok']);
        $this->assertSame(1, WalletTransfer::where('kind', 'refund')->count());
        $this->assertSame(0, $driver->wallet->fresh()->balance_kobo);

        // A double-click / simultaneous retry returns the same booking ID.
        $run = $open();
        $key = (string) Str::uuid();
        $action = ['action' => 'book', 'user' => $student->id, 'run' => $run->id, 'key' => $key];
        $results = $this->race([$action, $action]);
        $this->assertTrue($results[0]['ok'] && $results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, $run->activeBookings()->count());
        $service->transition($driver, $run->id, 'cancelled');

        // A walk-in update and online reservation lock the very same run.
        $run = $open();
        $service->updateRun($driver, $run->id, 17, null);
        $results = $this->race([
            ['action' => 'book', 'user' => $student->id, 'run' => $run->id, 'key' => (string) Str::uuid()],
            ['action' => 'walk', 'user' => $driver->id, 'run' => $run->id, 'count' => 18],
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(18, $run->fresh()->walk_in_count + $run->activeBookings()->count());
        $service->transition($driver, $run->id, 'cancelled');

        // The student's row serializes bookings on different buses.
        $run = $open();
        $driver2 = User::where('email', 'driver12@ful.test')->firstOrFail();
        $bus2 = $driver2->buses()->firstOrFail();
        $fare2 = Fare::where('bus_type', $bus2->type)->firstOrFail();
        $run2 = $service->openRun($driver2, $bus2->id, $fare2->id, now()->addHour()->toDateTimeString());
        $results = $this->race([
            ['action' => 'book', 'user' => $student->id, 'run' => $run->id, 'key' => (string) Str::uuid()],
            ['action' => 'book', 'user' => $student->id, 'run' => $run2->id, 'key' => (string) Str::uuid()],
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(1, Booking::where('student_id', $student->id)->where('status', 'booked')->count());
        $this->assertSame(80000, $student->wallet->fresh()->balance_kobo);
    }
}
