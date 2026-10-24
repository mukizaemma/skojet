<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GalleryMediaController extends Controller
{
    public function show(Gallery $gallery): BinaryFileResponse
    {
        $path = $gallery->absoluteImagePath();
        if ($path === null) {
            abort(404);
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
