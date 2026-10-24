<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('why_choose_us_items')
            ->whereIn(DB::raw('LOWER(title)'), [
                'private transfers',
                'private transfer',
                'transferts privés',
                'transfert privé',
            ])
            ->update([
                'title' => 'Competitive rates',
                'description' => 'Clear nightly rates for each suite and meal plan, with offers shown when they are active.',
                'icon' => 'fa-solid fa-tag',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('why_choose_us_items')
            ->where('title', 'Competitive rates')
            ->update([
                'title' => 'Private transfers',
                'description' => 'Transfers to the Kinigi RDB station and around Musanze for gorilla trekking and other park days.',
                'icon' => 'fa-solid fa-car',
                'updated_at' => now(),
            ]);
    }
};
