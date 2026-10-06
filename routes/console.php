<?php

use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\User;
use App\Services\ShuttleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

Artisan::command('demo:boarding', function () {
    if (app()->environment('production') && ! config('app.allow_demo_seed')) {
        $this->error('Set RUN_SEEDERS=true to enable coursework demo boarding in production.');

        return 1;
    }
    $opened = 0;
    foreach (DatabaseSeeder::BUSES as $index => [$capacity]) {
        $opened += DB::transaction(function () use ($capacity, $index) {
            // Match only the seed's bus/driver pairs and use the service's lock order.
            $driver = User::where('email', 'driver'.$capacity.'@ful.test')->where('role', 'driver')->lockForUpdate()->first();
            if (! $driver) {
                return 0;
            }
            $bus = Bus::where('identifier', 'FUL-'.str_pad((string) $capacity, 3, '0', STR_PAD_LEFT))
                ->where('driver_id', $driver->id)->where('approval_status', 'approved')->lockForUpdate()->first();
            if (! $bus || Run::whereIn('status', ['boarding', 'departed'])
                ->where(fn ($q) => $q->where('driver_id', $driver->id)->orWhere('bus_id', $bus->id))->exists()) {
                return 0;
            }
            $fare = Fare::where('bus_type', $bus->type)->where('active', true)
                ->whereColumn('origin_id', '!=', 'destination_id')
                ->whereHas('origin', fn ($q) => $q->where('active', true))
                ->whereHas('destination', fn ($q) => $q->where('active', true))
                ->orderBy('id')->first();
            if (! $fare) {
                return 0;
            }
            app(ShuttleService::class)->openRun($driver, $bus->id, $fare->id, now()->addMinutes(20 + $index * 10)->toDateTimeString());

            return 1;
        }, 5);
    }
    $this->info("Opened {$opened} sample boarding run(s). Existing active runs were kept.");
})->purpose('Open sample boarding runs for the coursework demonstration');

Artisan::command('app:create-admin', function () {
    $name = $this->ask('Administrator name');
    $email = strtolower((string) $this->ask('Administrator email'));
    $password = $this->secret('Password (at least 12 characters)');
    validator(compact('name', 'email', 'password'), ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:255', 'password' => ['required', Password::min(12)]])->validate();
    User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin']);
    $this->info('Administrator created.');
})->purpose('Create a real administrator without seeding demo accounts');
