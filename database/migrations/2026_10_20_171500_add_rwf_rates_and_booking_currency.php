<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->decimal('price_rwf', 12, 2)->nullable()->after('price');
        });

        Schema::table('extra_items', function (Blueprint $table) {
            $table->decimal('price_rwf', 12, 2)->nullable()->after('price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('currency', 8)->default('usd')->after('payment_status');
        });

        Schema::table('room_discounts', function (Blueprint $table) {
            $table->decimal('amount_rwf', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('room_discounts', function (Blueprint $table) {
            $table->dropColumn('amount_rwf');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('extra_items', function (Blueprint $table) {
            $table->dropColumn('price_rwf');
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('price_rwf');
        });
    }
};
