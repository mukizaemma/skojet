<?php

namespace App\Http\Middleware;

use App\Support\OptimizedImage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptimizePublicImages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldSkip($request, $response)) {
            return $response;
        }

        $html = $response->getContent();
        if (! is_string($html) || $html === '' || ! str_contains($html, '/storage/')) {
            return $response;
        }

        $rewritten = preg_replace_callback(
            '#/storage/(?!cache/img/)([^"\'\s)]+?\.(?:jpe?g|webp))#i',
            static function (array $matches): string {
                $path = rawurldecode($matches[1]);
                $suffix = OptimizedImage::cacheSuffix($path);
                if ($suffix === null) {
                    return $matches[0];
                }

                return '/storage/'.$suffix;
            },
            $html
        );

        if (is_string($rewritten)) {
            $response->setContent($rewritten);
        }

        return $response;
    }

    private function shouldSkip(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return true;
        }

        $path = ltrim($request->path(), '/');
        foreach (['content-management', 'admin', 'account', 'livewire', 'storage', 'setting', 'api', 'media'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return true;
        }

        return ! method_exists($response, 'getContent');
    }
}
