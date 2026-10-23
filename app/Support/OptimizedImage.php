<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Builds a cache URL for a resized public photo and writes that file on first request.
 * Originals stay untouched. The version in the URL changes when the source file changes.
 */
class OptimizedImage
{
    /** @var list<int> */
    public const WIDTHS = [640, 960, 1280, 1600, 1920];

    public static function url(?string $relativePath, ?int $width = null): string
    {
        $relativePath = self::normalize($relativePath);
        if ($relativePath === '') {
            return '';
        }

        $fallback = asset('storage/'.$relativePath);
        $suffix = self::cacheSuffix($relativePath, $width);
        if ($suffix === null) {
            return $fallback;
        }

        return asset('storage/'.$suffix);
    }

    /**
     * Same URL as url(), but writes the resized file first when it is missing.
     * Use this for the one image that should be ready with the first paint.
     */
    public static function ensure(?string $relativePath, ?int $width = null): string
    {
        $relativePath = self::normalize($relativePath);
        $url = self::url($relativePath, $width);
        $suffix = self::cacheSuffix($relativePath, $width);
        if ($suffix === null) {
            return $url;
        }

        $destination = storage_path('app/public/'.$suffix);
        if (! is_file($destination)) {
            try {
                self::write(storage_path('app/public/'.$relativePath), $destination, self::normalizeWidth($width ?? self::widthFor($relativePath)));
            } catch (RuntimeException $e) {
                return asset('storage/'.$relativePath);
            }
        }

        return $url;
    }

    public static function cacheSuffix(?string $relativePath, ?int $width = null): ?string
    {
        $relativePath = self::normalize($relativePath);
        if ($relativePath === '' || ! self::isOptimizable($relativePath)) {
            return null;
        }

        $source = storage_path('app/public/'.$relativePath);
        if (! is_file($source)) {
            return null;
        }

        $width = self::normalizeWidth($width ?? self::widthFor($relativePath));
        $version = (string) filemtime($source);

        return 'cache/img/w'.$width.'/v'.$version.'/'.$relativePath;
    }

    public static function widthFor(string $relativePath): int
    {
        $relativePath = self::normalize($relativePath);
        if (preg_match('#^(slides|page-heroes)/#', $relativePath) === 1) {
            return 1600;
        }

        return 1280;
    }

    public static function normalizeWidth(int $width): int
    {
        return in_array($width, self::WIDTHS, true) ? $width : 1280;
    }

    public static function isOptimizable(string $relativePath): bool
    {
        return preg_match('/\.(jpe?g|webp)$/i', $relativePath) === 1
            && ! str_starts_with($relativePath, 'cache/');
    }

    /**
     * @return non-empty-string
     */
    public static function destination(int $width, string $version, string $relativePath): string
    {
        return storage_path('app/public/cache/img/w'.$width.'/v'.$version.'/'.$relativePath);
    }

    public static function write(string $source, string $destination, int $width): void
    {
        $info = @getimagesize($source);
        if ($info === false) {
            throw new RuntimeException('Unreadable image.');
        }

        [$srcW, $srcH, $type] = $info;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if ($src === false) {
            throw new RuntimeException('Unsupported image.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $targetW = min($width, max(1, $srcW));
        $targetH = (int) max(1, round($srcH * ($targetW / max(1, $srcW))));

        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($dst === false) {
            imagedestroy($src);
            throw new RuntimeException('Could not create image.');
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);
        imageinterlace($dst, true);

        $dir = dirname($destination);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            imagedestroy($dst);
            throw new RuntimeException('Could not create image cache directory.');
        }

        $tmp = $destination.'.'.getmypid().'.tmp';
        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
        $written = $extension === 'webp' && function_exists('imagewebp')
            ? imagewebp($dst, $tmp, 72)
            : imagejpeg($dst, $tmp, 72);
        imagedestroy($dst);

        if (! $written) {
            @unlink($tmp);
            throw new RuntimeException('Could not encode image.');
        }

        if (! @rename($tmp, $destination)) {
            @unlink($tmp);
            throw new RuntimeException('Could not store image.');
        }
    }

    public static function normalize(?string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', (string) $relativePath);
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            return '';
        }

        return $relativePath;
    }
}
