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
            $table->date('booked_checkin_date')->nullable()->after('checkout_date');
            $table->date('booked_checkout_date')->nullable()->after('booked_checkin_date');
            $table->text('checkin_note')->nullable()->after('booked_checkout_date');
            $table->text('checkout_note')->nullable()->after('checkin_note');
            $table->decimal('charges_amount', 12, 2)->default(0)->after('total_amount');
        });

        DB::table('bookings')->whereNull('booked_checkin_date')->update([
            'booked_checkin_date' => DB::raw('checkin_date'),
            'booked_checkout_date' => DB::raw('checkout_date'),
        ]);

        Schema::create('extra_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 20);
            $table->string('kind', 30);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('booking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('extra_item_id')->nullable()->constrained('extra_items')->nullOnDelete();
            $table->string('name');
            $table->string('category', 20);
            $table->string('kind', 30);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->boolean('included')->default(false);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_charges');
        Schema::dropIfExists('extra_items');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'booked_checkin_date',
                'booked_checkout_date',
                'checkin_note',
                'checkout_note',
                'charges_amount',
            ]);
        });
    }
};
