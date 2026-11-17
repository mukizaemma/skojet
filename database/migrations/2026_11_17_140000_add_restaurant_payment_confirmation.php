<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'momo_code')) {
                $table->string('momo_code')->nullable();
            }
            if (! Schema::hasColumn('restaurants', 'momo_account_name')) {
                $table->string('momo_account_name')->nullable();
            }
        });

        if (! Schema::hasTable('restaurant_reservations')) {
            return;
        }

        Schema::table('restaurant_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_reservations', 'access_token')) {
                $table->string('access_token', 80)->nullable()->unique();
            }
            if (! Schema::hasColumn('restaurant_reservations', 'payer_name')) {
                $table->string('payer_name')->nullable();
            }
            if (! Schema::hasColumn('restaurant_reservations', 'payment_choice')) {
                $table->string('payment_choice', 20)->default('minimum');
            }
            if (! Schema::hasColumn('restaurant_reservations', 'claimed_amount')) {
                $table->decimal('claimed_amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('restaurant_reservations', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('restaurant_reservations', 'momo_code')) {
                $table->string('momo_code')->nullable();
            }
            if (! Schema::hasColumn('restaurant_reservations', 'momo_account_name')) {
                $table->string('momo_account_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            foreach (['momo_code', 'momo_account_name'] as $column) {
                if (Schema::hasColumn('restaurants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (! Schema::hasTable('restaurant_reservations')) {
            return;
        }

        Schema::table('restaurant_reservations', function (Blueprint $table) {
            foreach (['access_token', 'payer_name', 'payment_choice', 'claimed_amount', 'paid_amount', 'momo_code', 'momo_account_name'] as $column) {
                if (Schema::hasColumn('restaurant_reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
