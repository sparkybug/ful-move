<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Fare;
use App\Models\Terminal;
use App\Models\User;
use App\Services\ShuttleService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public const BUSES = [[60, 'Coach', 'Musa Ibrahim'], [18, 'Minibus', 'Joseph Ameh'], [12, 'Shuttle', 'Grace Ali'], [10, 'Van', 'Samuel James']];

    public function run(): void
    {
        if (app()->environment('production') && ! config('app.allow_demo_seed')) {
            throw new \RuntimeException('Set RUN_SEEDERS=true to initialize the coursework demo in production.');
        }

        $admin = User::firstOrCreate(['email' => 'admin@ful.test'], ['name' => 'Transport Admin', 'role' => 'admin', 'password' => 'Campus@2026']);
        foreach (['student@ful.test' => ['Amina Yusuf', 'FUL/2026/001'], 'student2@ful.test' => ['David Okafor', 'FUL/2026/002']] as $email => [$name, $identifier]) {
            $student = User::firstOrCreate(['email' => $email], ['name' => $name, 'student_identifier' => $identifier, 'role' => 'student', 'password' => 'Campus@2026']);
            app(ShuttleService::class)->credit($admin, $student, 100000, 'Initial coursework transport credit', 'seed-'.$student->id);
        }
        $origin = Terminal::firstOrCreate(['name' => 'Felele Campus']);
        $destination = Terminal::firstOrCreate(['name' => 'Adankolo Campus']);
        foreach (self::BUSES as [$capacity, $type, $name]) {
            $driver = User::firstOrCreate(['email' => 'driver'.$capacity.'@ful.test'], ['name' => $name, 'role' => 'driver', 'password' => 'Campus@2026']);
            Bus::firstOrCreate(['identifier' => 'FUL-'.str_pad((string) $capacity, 3, '0', STR_PAD_LEFT)], ['driver_id' => $driver->id, 'type' => $type, 'capacity' => $capacity, 'ownership_details' => 'Coursework demonstration bus', 'approval_status' => 'approved']);
            foreach ([[$origin->id, $destination->id], [$destination->id, $origin->id]] as [$from, $to]) {
                Fare::firstOrCreate(['origin_id' => $from, 'destination_id' => $to, 'bus_type' => $type], ['amount_kobo' => 20000, 'active' => true]);
            }
        }
    }
}
