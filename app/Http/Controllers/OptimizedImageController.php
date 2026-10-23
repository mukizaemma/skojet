<?php

namespace App\Http\Controllers;

use App\Support\OptimizedImage;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OptimizedImageController extends Controller
{
    public function show(int $width, string $version, string $path): BinaryFileResponse|RedirectResponse
    {
        $path = OptimizedImage::normalize(rawurldecode($path));
        $width = OptimizedImage::normalizeWidth($width);

        if ($path === '' || ! OptimizedImage::isOptimizable($path) || preg_match('/^[0-9]+$/', $version) !== 1) {
            abort(404);
        }

        $source = storage_path('app/public/'.$path);
        $base = realpath(storage_path('app/public'));
        $real = realpath($source);
        if ($base === false || $real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $destination = OptimizedImage::destination($width, $version, $path);
        if (! is_file($destination)) {
            try {
                OptimizedImage::write($real, $destination, $width);
            } catch (Throwable $e) {
                return redirect()->to(asset('storage/'.$path));
            }
        }

        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

        return response()->file($destination, [
            'Content-Type' => $extension === 'webp' ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
