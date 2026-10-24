<?php

use App\Support\HotelTerms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('terms_conditions')->orderBy('id')->first();

        if ($existing && ! HotelTerms::isStale($existing->content ?? '')) {
            return;
        }

        $row = [
            'content' => HotelTerms::html(),
            'status' => 'active',
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('terms_conditions')->where('id', $existing->id)->update($row);

            return;
        }

        DB::table('terms_conditions')->insert($row + [
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Layout copy is replaced in place.
    }
};
