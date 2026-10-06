<?php

use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\User;
use App\Services\ShuttleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rules\Password;

Artisan::command('demo:boarding', function () {
    if (app()->environment('production')) {
        $this->error('Demo boarding is disabled in production.');

        return 1;
    }
    foreach (Bus::where('approval_status', 'approved')->with('driver')->get() as $index => $bus) {
        if (Run::whereIn('status', ['boarding', 'departed'])->where(fn ($q) => $q->where('driver_id', $bus->driver_id)->orWhere('bus_id', $bus->id))->exists()) {
            continue;
        }
        $fare = Fare::where('bus_type', $bus->type)->where('active', true)->first();
        if ($fare) {
            app(ShuttleService::class)->openRun($bus->driver, $bus->id, $fare->id, now()->addMinutes(20 + $index * 10)->toDateTimeString());
        }
    }
    $this->info('Example buses are boarding. Existing active runs were kept.');
})->purpose('Open sample boarding runs for the coursework demonstration');

Artisan::command('app:create-admin', function () {
    $name = $this->ask('Administrator name');
    $email = strtolower((string) $this->ask('Administrator email'));
    $password = $this->secret('Password (at least 12 characters)');
    validator(compact('name', 'email', 'password'), ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:255', 'password' => ['required', Password::min(12)]])->validate();
    User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin']);
    $this->info('Administrator created.');
})->purpose('Create a real administrator without seeding demo accounts');
