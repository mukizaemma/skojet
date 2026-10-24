<div class="public-livewire-page skojet-coffee-page">

@include('frontend.includes.page-hero-banner', [
    'defaultCaption' => $restaurant->title ?? site_trans('dining.title'),
    'defaultDescription' => site_trans('dining.hero_lead'),
])

@php
    $galleryImages = $images ?? collect();
    $restaurantBgImage = filled($restaurant->image ?? null)
        ? asset('storage/images/restaurant/' . $restaurant->image)
        : (filled($images?->first()?->image) ? asset('storage/images/restaurant/' . $images->first()->image) : null);

    $highlightImages = collect();
    foreach ($galleryImages as $img) {
        if (filled($img->image ?? null)) {
            $highlightImages->push([
                'url' => asset('storage/images/restaurant/' . $img->image),
                'alt' => filled($img->caption) ? $img->caption : ($restaurant->title ?? site_trans('dining.title')),
            ]);
        }
    }
    if ($highlightImages->isEmpty() && $restaurantBgImage) {
        $highlightImages->push([
            'url' => $restaurantBgImage,
            'alt' => site_trans('dining.title'),
        ]);
    }

    $offerings = [
        ['icon' => 'fa-solid fa-mug-hot', 'title' => site_trans('dining.why_1_title'), 'text' => site_trans('dining.why_1_text')],
        ['icon' => 'fa-solid fa-seedling', 'title' => site_trans('dining.why_2_title'), 'text' => site_trans('dining.why_2_text')],
        ['icon' => 'fa-solid fa-utensils', 'title' => site_trans('dining.why_3_title'), 'text' => site_trans('dining.why_3_text')],
        ['icon' => 'fa-solid fa-wine-glass', 'title' => site_trans('dining.why_4_title'), 'text' => site_trans('dining.why_4_text')],
        ['icon' => 'fa-solid fa-beer', 'title' => site_trans('dining.why_5_title'), 'text' => site_trans('dining.why_5_text')],
        ['icon' => 'fa-solid fa-sun', 'title' => site_trans('dining.why_6_title'), 'text' => site_trans('dining.why_6_text')],
    ];

    $ctaSetting = $setting ?? \App\Models\Setting::first();
@endphp

{{-- The table --}}
<section class="rts__section section__padding skojet-coffee-menu" aria-labelledby="coffee-menu-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.why_eyebrow') }}</span>
            <h2 id="coffee-menu-heading" class="skojet-intro__title">{{ site_trans('dining.pillars_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.why_lead') }}</p>
        </header>
        <div class="row g-3 g-lg-4">
            @foreach ($offerings as $item)
            <div class="col-md-6 col-xl-4">
                <article class="skojet-coffee-menu__item">
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <strong>{{ $item['title'] }}</strong>
                    <span>{{ $item['text'] }}</span>
                </article>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Gallery highlights --}}
<section class="rts__section section__padding pt-0 skojet-coffee-gallery" aria-labelledby="coffee-gallery-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.gallery_eyebrow') }}</span>
            <h2 id="coffee-gallery-heading" class="skojet-intro__title">{{ site_trans('dining.gallery_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.gallery_lead') }}</p>
        </header>

        @if($highlightImages->isNotEmpty())
            <div class="skojet-coffee-gallery__grid">
                @foreach ($highlightImages as $shot)
                    <figure class="skojet-coffee-gallery__item">
                        <img src="{{ $shot['url'] }}"
                             alt="{{ $shot['alt'] }}"
                             loading="lazy"
                             width="900"
                             height="700">
                    </figure>
                @endforeach
            </div>
        @else
            <div class="skojet-coffee-gallery__empty">
                <p class="mb-0">{{ site_trans('dining.gallery_lead') }}</p>
                <p class="small text-muted mt-2 mb-0">Add coffee shop photos in Content Management → Dining / Restaurant gallery.</p>
            </div>
        @endif

        @if($galleryImages->isNotEmpty())
            <div class="mt-4 mt-lg-5 d-none">
                @include('frontend.includes.page-gallery', [
                    'galleryImages' => $galleryImages,
                    'storageSubfolder' => 'restaurant',
                    'galleryKey' => 'dining',
                ])
            </div>
        @endif
    </div>
</section>

{{-- CTA --}}
<section class="rts__section section__padding skojet-book-band" aria-labelledby="coffee-cta-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.eyebrow') }}</span>
            <h2 id="coffee-cta-heading" class="skojet-intro__title">{{ site_trans('dining.cta_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.cta_lead') }}</p>
        </header>
        <div class="skojet-book-band__actions">
            <a wire:navigate href="{{ localized_route('contact') }}" class="theme-btn btn-style fill">
                <span>{{ site_trans('buttons.contact_us') }}</span>
            </a>
            <a wire:navigate href="{{ localized_route('connect') }}" class="theme-btn btn-style border">
                <span>{{ hotel_book_now_label() }}</span>
            </a>
        </div>
    </div>
</section>
</div>
