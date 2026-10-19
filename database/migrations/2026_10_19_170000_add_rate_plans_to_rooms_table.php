<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'rate_plans')) {
                $table->json('rate_plans')->nullable()->after('bed_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'rate_plans')) {
                $table->dropColumn('rate_plans');
            }
        });
    }
};
