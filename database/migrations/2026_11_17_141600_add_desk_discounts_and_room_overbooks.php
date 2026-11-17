<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'manual_discount_type')) {
                $table->string('manual_discount_type', 20)->nullable()->after('discount_label');
            }
            if (! Schema::hasColumn('bookings', 'manual_discount_value')) {
                $table->decimal('manual_discount_value', 12, 2)->nullable()->after('manual_discount_type');
            }
        });

        if (! Schema::hasTable('room_overbooks')) {
            Schema::create('room_overbooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->unsignedSmallInteger('extra')->default(1);
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_overbooks');

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'manual_discount_value')) {
                $table->dropColumn('manual_discount_value');
            }
            if (Schema::hasColumn('bookings', 'manual_discount_type')) {
                $table->dropColumn('manual_discount_type');
            }
        });
    }
};
