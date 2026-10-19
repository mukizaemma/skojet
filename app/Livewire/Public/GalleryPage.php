<?php

namespace App\Livewire\Public;

use App\Models\Gallery;
use App\Services\PublicWebsiteData;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.frontbase')]
class GalleryPage extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public function render()
    {
        $page = Gallery::query()
            ->where('media_type', 'image')
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

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
