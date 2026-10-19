<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Gallery extends Model
{
    use HasFactory;
        protected $table = "galleries";

        protected $fillable = [
            'media_type',
            'category',
            'caption',
            'image',
            'video_path',
            'youtube_link',
            'thumbnail',
            'sort_order',
            'show_on_home',
        ];

        protected $casts = [
            'show_on_home' => 'boolean',
            'sort_order' => 'integer',
        ];

    /**
     * Public URL for this image.
     * Newer uploads store a path such as gallery/photo.jpg.
     * Older uploads store only the file name, and the file lives in images/gallery.
     */
    public function publicImageUrl(): string
    {
        $path = ltrim((string) $this->image, '/');
        if ($path === '') {
            return '';
        }

        if (str_contains($path, '/')) {
            return asset('storage/'.$path);
        }

        $legacy = 'images/gallery/'.$path;
        if (Storage::disk('public')->exists($legacy)) {
            return asset('storage/'.$legacy);
        }

        if (Storage::disk('public')->exists('gallery/'.$path)) {
            return asset('storage/gallery/'.$path);
        }

        return asset('storage/'.$legacy);
    }

    /**
     * Get YouTube video ID from youtube_link for embedding.
     */
    public function getYoutubeVideoIdAttribute(): ?string
    {
        if (empty($this->youtube_link)) {
            return null;
        }
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $this->youtube_link, $m)) {
            return $m[1];
        }
        return null;
    }
}
