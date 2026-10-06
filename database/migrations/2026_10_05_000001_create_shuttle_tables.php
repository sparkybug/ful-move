<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'driver', 'admin'])->default('student');
            $table->string('student_identifier')->nullable()->unique();
        });
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->string('identifier')->unique();
            $table->string('type');
            $table->unsignedSmallInteger('capacity');
            $table->string('ownership_details');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
        Schema::create('fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained('terminals');
            $table->foreignId('destination_id')->constrained('terminals');
            $table->string('bus_type');
            $table->unsignedBigInteger('amount_kobo');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['origin_id', 'destination_id', 'bus_type']);
        });
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained();
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('origin_id')->constrained('terminals');
            $table->foreignId('destination_id')->constrained('terminals');
            $table->unsignedSmallInteger('capacity_snapshot');
            $table->unsignedBigInteger('fare_snapshot_kobo');
            $table->unsignedSmallInteger('walk_in_count')->default(0);
            $table->dateTime('estimated_departure_at');
            $table->dateTime('estimate_updated_at');
            $table->enum('status', ['boarding', 'departed', 'arrived', 'cancelled'])->default('boarding');
            $table->unsignedBigInteger('active_driver_id')->nullable()->storedAs("CASE WHEN status IN ('boarding', 'departed') THEN driver_id ELSE NULL END")->unique();
            $table->unsignedBigInteger('active_bus_id')->nullable()->storedAs("CASE WHEN status IN ('boarding', 'departed') THEN bus_id ELSE NULL END")->unique();
            $table->timestamps();
            $table->index(['status', 'estimated_departure_at']);
        });
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->unsignedBigInteger('balance_kobo')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained();
            $table->foreignId('student_id')->constrained('users');
            $table->string('booking_code', 20)->unique();
            $table->unsignedBigInteger('fare_kobo');
            $table->enum('status', ['booked', 'boarded', 'cancelled'])->default('booked');
            $table->unsignedTinyInteger('active_marker')->nullable()->storedAs("CASE WHEN status IN ('booked', 'boarded') THEN 1 ELSE NULL END");
            $table->timestamps();
            $table->unique(['run_id', 'student_id', 'active_marker']);
        });
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 120)->unique();
            $table->enum('kind', ['credit', 'booking', 'refund']);
            $table->foreignId('booking_id')->nullable()->constrained();
            $table->foreignId('recipient_id')->nullable()->constrained('users');
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedBigInteger('amount_kobo');
            $table->foreignId('original_transfer_id')->nullable()->unique()->constrained('wallet_transfers');
            $table->string('reason');
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('payment_transfer_id')->nullable()->unique()->constrained('wallet_transfers');
        });
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('transfer_id')->constrained('wallet_transfers');
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedBigInteger('balance_after_kobo');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('reason');
            $table->timestamps();
            $table->unique(['wallet_id', 'transfer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_transfer_id'));
        Schema::dropIfExists('wallet_transfers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('runs');
        Schema::dropIfExists('fares');
        Schema::dropIfExists('buses');
        Schema::dropIfExists('terminals');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'student_identifier']));
    }
};
