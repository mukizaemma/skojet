<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class HotelSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if settings table exists and is empty
        if (!Schema::hasTable('settings')) {
            return;
        }

        // Only seed if settings table is empty
        if (DB::table('settings')->count() > 0) {
            return;
        }

        $superAdmin = User::where('email', 'admin@lucernakabgayihotel.rw')->first();

        DB::table('settings')->insert([
            'title' => 'Skojet Boutique Hotel',
            'company' => 'Skojet Boutique Hotel',
            'quote' => 'Stay · Relax · Unwind',
            'address' => 'Kinigi, Musanze, Rwanda',
            'phone' => '+250 788 000 000',
            'email' => 'info@skojethotel.com',
            'facebook' => null,
            'instagram' => null,
            'twitter' => null,
            'youtube' => null,
            'linkedin' => null,
            'linktree' => null,
            'donate' => null,
            'logo' => null,
            'footer_delivered_by_enabled' => true,
            'footer_delivered_by_company' => 'Ireme Tech',
            'footer_delivered_by_url' => 'https://iremetech.com',
            'user_id' => $superAdmin ? $superAdmin->id : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
