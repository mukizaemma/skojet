<?php

namespace App\Livewire\Public;

use App\Models\Gallery;
use App\Services\PublicWebsiteData;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.frontbase')]
class GalleryPage extends Component
{
    public function render()
    {
        Gallery::reconcileImagePaths();
        Gallery::syncExistingServerPhotos();

        $seen = [];
        $photos = Gallery::query()
            ->photos()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (Gallery $image) use (&$seen) {
                $absolute = $image->absoluteImagePath();
                if ($absolute === null || $image->publicImageUrl() === '') {
                    return false;
                }

                $hash = md5_file($absolute);
                if (isset($seen[$hash])) {
                    return false;
                }

                $seen[$hash] = true;

                return true;
            })
            ->values();

        $perPage = 15;
        $lastPage = max(1, (int) ceil($photos->count() / $perPage));
        $pageNumber = min($lastPage, max(1, (int) request()->query('page', 1)));
        $page = new LengthAwarePaginator(
            $photos->forPage($pageNumber, $perPage)->values(),
            $photos->count(),
            $perPage,
            $pageNumber,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        $galleryImages = $page->getCollection()->map(fn (Gallery $image) => [
            'key' => 'gallery-'.$image->id,
            'url' => $image->publicImageUrl(),
            'caption' => (string) ($image->caption ?? ''),
        ])->filter(fn (array $image) => $image['url'] !== '')->values();

        return view('frontend.gallery', array_merge(PublicWebsiteData::galleryPageStatic(), [
            'galleryImages' => $galleryImages,
            'galleryPaginator' => $page,
        ]));
    }
}
