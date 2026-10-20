<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reservation_channel', 20)->nullable()->after('booking_type');
            $table->string('guest_source', 20)->nullable()->after('reservation_channel');
        });

        DB::table('bookings')->where('booking_type', 'online')->whereNull('reservation_channel')->update([
            'reservation_channel' => 'website',
        ]);
        DB::table('bookings')->where('booking_type', 'walkin')->whereNull('reservation_channel')->update([
            'reservation_channel' => 'walkin',
        ]);
        DB::table('bookings')->whereNull('guest_source')->update([
            'guest_source' => 'unspecified',
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reservation_channel', 'guest_source']);
        });
    }
};
