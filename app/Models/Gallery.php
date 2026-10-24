<?php

namespace App\Models;

use App\Services\PublicWebsiteData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
     * Folders that already hold hotel photos. The public gallery uses one
     * copy of each photo from these folders.
     *
     * @var list<string>
     */
    private const SERVER_PHOTO_DIRECTORIES = [
        'gallery',
        'images/gallery',
        'rooms/gallery',
        'rooms',
        'slides',
        'page-heroes',
        'images/restaurant',
    ];

    /**
     * Add a gallery row for each unique photo already stored on the server.
     * Copies of the same file are skipped.
     */
    public static function syncExistingServerPhotos(): void
    {
        $files = static::serverPhotoFiles();
        $stamp = count($files).':'.static::serverPhotoStamp($files);
        if (Cache::get('gallery.server-photos.stamp') === $stamp && static::query()->photos()->exists()) {
            return;
        }

        $known = [];
        foreach (static::query()->photos()->get() as $row) {
            $absolute = $row->absoluteImagePath();
            if ($absolute !== null && is_file($absolute)) {
                $known[md5_file($absolute)] = true;
            }
        }

        $sort = (int) static::query()->max('sort_order');
        $homeCount = (int) static::query()->where('show_on_home', true)->count();
        $created = 0;

        foreach (static::uniqueServerPhotoPaths($files) as $relative) {
            $absolute = storage_path('app/public/'.$relative);
            if (! is_file($absolute)) {
                continue;
            }

            $hash = md5_file($absolute);
            if (isset($known[$hash])) {
                continue;
            }

            $sort++;
            $onHome = $homeCount < 6;
            if ($onHome) {
                $homeCount++;
            }

            static::query()->create([
                'media_type' => 'image',
                'image' => $relative,
                'sort_order' => $sort,
                'show_on_home' => $onHome,
            ]);
            $known[$hash] = true;
            $created++;
        }

        Cache::put('gallery.server-photos.stamp', $stamp, now()->addDay());
        if ($created > 0) {
            PublicWebsiteData::flushCaches();
        }
    }

    /**
     * One stored path per unique photo. A file already in gallery/ wins over
     * the same photo stored again under rooms, slides, or page heroes.
     *
     * @param  list<string>|null  $files
     * @return list<string>
     */
    public static function uniqueServerPhotoPaths(?array $files = null): array
    {
        $best = [];

        foreach ($files ?? static::serverPhotoFiles() as $relative) {
            $absolute = storage_path('app/public/'.$relative);
            if (! is_file($absolute)) {
                continue;
            }

            $hash = md5_file($absolute);
            $priority = str_starts_with($relative, 'gallery/') ? 0 : 1;
            if (! isset($best[$hash]) || $priority < $best[$hash]['priority'] || ($priority === $best[$hash]['priority'] && strcmp($relative, $best[$hash]['path']) < 0)) {
                $best[$hash] = ['path' => $relative, 'priority' => $priority];
            }
        }

        $paths = array_column($best, 'path');
        sort($paths);

        return array_values($paths);
    }

    /**
     * @return list<string>
     */
    public static function serverPhotoFiles(): array
    {
        $files = [];

        foreach (self::SERVER_PHOTO_DIRECTORIES as $directory) {
            if (! Storage::disk('public')->exists($directory)) {
                continue;
            }

            foreach (Storage::disk('public')->files($directory) as $relative) {
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }

                $absolute = storage_path('app/public/'.$relative);
                if (! is_file($absolute) || filesize($absolute) < 20000) {
                    continue;
                }

                $files[] = $relative;
            }
        }

        return $files;
    }

    /**
     * @param  list<string>  $files
     */
    private static function serverPhotoStamp(array $files): int
    {
        $latest = 0;
        foreach ($files as $relative) {
            $mtime = @filemtime(storage_path('app/public/'.$relative));
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }

        return $latest;
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
