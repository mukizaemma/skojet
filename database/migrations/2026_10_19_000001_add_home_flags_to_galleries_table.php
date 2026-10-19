<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            if (! Schema::hasColumn('galleries', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('galleries', 'show_on_home')) {
                $table->boolean('show_on_home')->default(false);
            }
        });

        $hasImages = DB::table('galleries')
            ->where('media_type', 'image')
            ->whereNotNull('image')
            ->exists();

        if ($hasImages) {
            return;
        }

        $candidates = [];

        foreach (DB::table('slides')->whereNotNull('image')->orderBy('sort_order')->orderBy('id')->get() as $slide) {
            $candidates[] = [
                'path' => (string) $slide->image,
                'caption' => $slide->heading ?? null,
            ];
        }

        if (Schema::hasTable('rooms')) {
            foreach (DB::table('rooms')->whereNotNull('cover_image')->orderBy('id')->get() as $room) {
                $candidates[] = [
                    'path' => (string) $room->cover_image,
                    'caption' => $room->title ?? null,
                ];
            }
        }

        if (Schema::hasTable('roomimages')) {
            foreach (DB::table('roomimages')->whereNotNull('image')->orderBy('id')->get() as $row) {
                $candidates[] = [
                    'path' => (string) $row->image,
                    'caption' => $row->caption ?? null,
                ];
            }
        }

        Storage::disk('public')->makeDirectory('gallery');

        $order = 1;
        $seen = [];

        foreach ($candidates as $candidate) {
            if ($order > 18) {
                break;
            }

            $path = ltrim($candidate['path'], '/');
            if ($path === '' || isset($seen[$path]) || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $seen[$path] = true;
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $dest = 'gallery/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->copy($path, $dest);

            DB::table('galleries')->insert([
                'media_type' => 'image',
                'image' => $dest,
                'caption' => $candidate['caption'],
                'sort_order' => $order,
                'show_on_home' => $order <= 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $order++;
        }
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            if (Schema::hasColumn('galleries', 'show_on_home')) {
                $table->dropColumn('show_on_home');
            }
            if (Schema::hasColumn('galleries', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
