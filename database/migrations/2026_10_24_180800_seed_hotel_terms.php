<?php

use App\Support\HotelTerms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('terms_conditions')->orderBy('id')->first();
        $plain = trim(strip_tags((string) ($existing->content ?? '')));

        if ($existing && $plain !== '') {
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
        DB::table('terms_conditions')
            ->where('content', HotelTerms::html())
            ->delete();
    }
};
