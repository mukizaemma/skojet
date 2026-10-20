<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('reservations_manager_enabled')->default(false)->after('booking_channel_enabled');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('board', 40)->nullable()->after('party_type');
            $table->string('occupancy', 20)->nullable()->after('board');
            $table->string('address', 255)->nullable()->after('country');
            $table->string('postal_code', 30)->nullable()->after('address');
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('note', 255)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('room_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_closures');
        Schema::dropIfExists('booking_payments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['board', 'occupancy', 'address', 'postal_code']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('reservations_manager_enabled');
        });
    }
};
