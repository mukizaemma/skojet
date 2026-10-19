<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('title');
            $table->string('discount_type');
            $table->decimal('amount', 10, 2);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('list_amount', 12, 2)->nullable()->after('total_amount');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('list_amount');
            $table->string('discount_label')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['list_amount', 'discount_amount', 'discount_label']);
        });

        Schema::dropIfExists('room_discounts');
    }
};
