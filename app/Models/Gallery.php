<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Photos managed on the gallery page, including older rows with no media type.
     */
    public function scopePhotos(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $inner) {
                $inner->where('media_type', 'image')->orWhereNull('media_type');
            })
            ->whereNotNull('image')
            ->where('image', '!=', '');
    }

    /**
     * URL that serves this photo through the app, so it still loads when the
     * public storage link or a stored path prefix is wrong.
     */
    public function publicImageUrl(): string
    {
        $absolute = $this->absoluteImagePath();
        if ($absolute === null || ! $this->exists) {
            return '';
        }

        return route('gallery.media', $this).'?v='.filemtime($absolute);
    }

    public function absoluteImagePath(): ?string
    {
        $relative = $this->storageRelativePath();
        if ($relative === null) {
            return null;
        }

        $base = realpath(storage_path('app/public'));
        $real = realpath(storage_path('app/public/'.$relative));
        if ($base === false || $real === false || ! is_file($real)) {
            return null;
        }

        $prefix = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($real, $prefix)) {
            return null;
        }

        return $real;
    }

    /**
     * Storage path relative to the public disk, or null when the file is missing.
     * Accepts gallery/photo.jpg, a bare filename, or a path that already
     * includes storage/ or public/.
     */
    public function storageRelativePath(): ?string
    {
        $normalized = $this->normalizeStoredImagePath((string) $this->image);
        if ($normalized !== '' && $this->publicFileExists($normalized)) {
            return $normalized;
        }

        $base = basename($normalized !== '' ? $normalized : (string) $this->image);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }

        foreach (['gallery/'.$base, 'images/gallery/'.$base] as $candidate) {
            if ($this->publicFileExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Point each row at a file that actually exists, and attach gallery files
     * that were saved on disk but never linked to a row.
     */
    public static function reconcileImagePaths(): void
    {
        $rows = static::query()
            ->where(function (Builder $query) {
                $query->where('media_type', 'image')->orWhereNull('media_type');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $used = [];

        foreach ($rows as $row) {
            $resolved = $row->storageRelativePath();
            if ($resolved === null) {
                continue;
            }

            $used[$resolved] = true;
            if ((string) $row->image !== $resolved) {
                $row->image = $resolved;
                $row->save();
            }
        }

        $unresolved = $rows
            ->filter(fn (self $row) => $row->storageRelativePath() === null)
            ->values();

        if ($unresolved->isEmpty()) {
            return;
        }

        $orphans = [];
        foreach (['gallery', 'images/gallery'] as $directory) {
            if (! Storage::disk('public')->exists($directory)) {
                continue;
            }

            foreach (Storage::disk('public')->files($directory) as $file) {
                if (isset($used[$file])) {
                    continue;
                }

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }

                $orphans[] = $file;
            }
        }

        sort($orphans);

        foreach ($unresolved as $index => $row) {
            if (! isset($orphans[$index])) {
                break;
            }

            $row->image = $orphans[$index];
            if (! filled($row->media_type)) {
                $row->media_type = 'image';
            }
            $row->save();
            $used[$orphans[$index]] = true;
        }
    }

    private function normalizeStoredImagePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? $path;
        $path = ltrim($path, '/');

        foreach (['public/storage/', 'storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return '';
        }

        return $path;
    }

    private function publicFileExists(string $relative): bool
    {
        return $relative !== '' && Storage::disk('public')->exists($relative);
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
