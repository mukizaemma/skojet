<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'requires_reservation')) {
                $table->boolean('requires_reservation')->default(true);
            }
            if (! Schema::hasColumn('restaurants', 'minimum_amount')) {
                $table->decimal('minimum_amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('restaurants', 'minimum_amount_local')) {
                $table->decimal('minimum_amount_local', 12, 2)->default(0);
            }
        });

        Schema::create('restaurant_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->decimal('price_international', 12, 2)->default(0);
            $table->decimal('price_local', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
        });

        Schema::create('restaurant_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('names');
            $table->string('phone');
            $table->string('email');
            $table->unsignedInteger('guests')->default(1);
            $table->dateTime('reserved_for');
            $table->text('notes')->nullable();
            $table->string('submit_via', 20)->default('email');
            $table->string('price_tier', 20)->default('international');
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('minimum_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['reserved_for', 'payment_status']);
        });

        Schema::create('restaurant_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_reservation_id')->constrained('restaurant_reservations')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('restaurant_menu_items')->nullOnDelete();
            $table->string('category', 20);
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->string('source', 20)->default('guest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reservation_items');
        Schema::dropIfExists('restaurant_reservations');
        Schema::dropIfExists('restaurant_menu_items');

        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'requires_reservation')) {
                $table->dropColumn('requires_reservation');
            }
            if (Schema::hasColumn('restaurants', 'minimum_amount')) {
                $table->dropColumn('minimum_amount');
            }
            if (Schema::hasColumn('restaurants', 'minimum_amount_local')) {
                $table->dropColumn('minimum_amount_local');
            }
        });
    }
};
