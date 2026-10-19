<div class="public-livewire-page skojet-coffee-page">

@include('frontend.includes.page-hero-banner', [
    'defaultCaption' => $restaurant->title ?? site_trans('dining.title'),
    'defaultDescription' => site_trans('dining.hero_lead'),
])

@php
    $galleryImages = $images ?? collect();
    $cuisineCards = isset($cuisines) ? $cuisines->filter(fn ($c) => filled($c->image)) : collect();
    $restaurantBgImage = filled($restaurant->image ?? null)
        ? asset('storage/images/restaurant/' . $restaurant->image)
        : (filled($images?->first()?->image) ? asset('storage/images/restaurant/' . $images->first()->image) : null);

    $highlightImages = collect();
    foreach ($galleryImages as $img) {
        if (filled($img->image ?? null)) {
            $highlightImages->push([
                'url' => asset('storage/images/restaurant/' . $img->image),
                'alt' => $restaurant->title ?? site_trans('dining.title'),
            ]);
        }
    }
    foreach ($cuisineCards as $cuisine) {
        $highlightImages->push([
            'url' => asset('storage/images/restaurant/cuisines/' . $cuisine->image),
            'alt' => $cuisine->title,
        ]);
    }
    if ($highlightImages->isEmpty() && $restaurantBgImage) {
        $highlightImages->push([
            'url' => $restaurantBgImage,
            'alt' => site_trans('dining.title'),
        ]);
    }

    $whyItems = [
        ['icon' => 'fa-solid fa-mug-hot', 'title' => site_trans('dining.why_1_title'), 'text' => site_trans('dining.why_1_text')],
        ['icon' => 'fa-solid fa-mountain-sun', 'title' => site_trans('dining.why_2_title'), 'text' => site_trans('dining.why_2_text')],
        ['icon' => 'fa-solid fa-person-hiking', 'title' => site_trans('dining.why_3_title'), 'text' => site_trans('dining.why_3_text')],
        ['icon' => 'fa-solid fa-heart', 'title' => site_trans('dining.why_4_title'), 'text' => site_trans('dining.why_4_text')],
    ];

    $menuPillars = [
        ['icon' => 'fa-solid fa-mug-saucer', 'title' => site_trans('dining.breakfast'), 'text' => site_trans('dining.breakfast_text')],
        ['icon' => 'fa-solid fa-bread-slice', 'title' => site_trans('dining.lunch'), 'text' => site_trans('dining.lunch_text')],
        ['icon' => 'fa-solid fa-leaf', 'title' => site_trans('dining.dinner'), 'text' => site_trans('dining.dinner_text')],
        ['icon' => 'fa-solid fa-glass-water', 'title' => site_trans('dining.beverages'), 'text' => site_trans('dining.beverages_text')],
        ['icon' => 'fa-solid fa-chair', 'title' => site_trans('dining.private'), 'text' => site_trans('dining.private_text')],
        ['icon' => 'fa-solid fa-seedling', 'title' => site_trans('dining.chef'), 'text' => site_trans('dining.chef_text')],
    ];

    $ctaSetting = $setting ?? \App\Models\Setting::first();
@endphp

{{-- Intro --}}
<section class="rts__section section__padding skojet-coffee-intro">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <header class="skojet-intro">
                    <span class="skojet-intro__eyebrow">{{ site_trans('dining.eyebrow') }}</span>
                    <h2 class="skojet-intro__title">{{ $restaurant->title ?? site_trans('dining.title') }}</h2>
                    <p class="skojet-intro__lead">{{ site_trans('dining.lead') }}</p>
                    <p class="skojet-coffee-tagline">Stay · Relax · Unwind</p>
                </header>
            </div>
        </div>
        @if(filled(strip_tags((string) ($restaurant->description ?? ''))))
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="skojet-coffee-story content-richtext text-center">
                        {!! $restaurant->description !!}
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

{{-- Why visit --}}
<section class="rts__section section__padding pt-0 skojet-coffee-why" aria-labelledby="coffee-why-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.why_eyebrow') }}</span>
            <h2 id="coffee-why-heading" class="skojet-intro__title">{{ site_trans('dining.why_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.why_lead') }}</p>
        </header>
        <div class="row g-3 g-lg-4">
            @foreach ($whyItems as $item)
            <div class="col-md-6 col-xl-3">
                <article class="skojet-coffee-why__card h-100">
                    <span class="skojet-coffee-why__icon" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></span>
                    <h3 class="skojet-coffee-why__title">{{ $item['title'] }}</h3>
                    <p class="skojet-coffee-why__text">{{ $item['text'] }}</p>
                </article>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- What we serve --}}
<section class="rts__section section__padding skojet-coffee-menu" aria-labelledby="coffee-menu-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.eyebrow') }}</span>
            <h2 id="coffee-menu-heading" class="skojet-intro__title">{{ site_trans('dining.pillars_title') }}</h2>
        </header>
        <div class="row g-3 g-lg-4">
            @foreach ($menuPillars as $pillar)
            <div class="col-md-6 col-xl-4">
                <article class="skojet-coffee-menu__item h-100">
                    <i class="{{ $pillar['icon'] }}" aria-hidden="true"></i>
                    <strong>{{ $pillar['title'] }}</strong>
                    <span>{{ $pillar['text'] }}</span>
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
                @foreach ($highlightImages->take(8) as $i => $shot)
                    <figure class="skojet-coffee-gallery__item skojet-coffee-gallery__item--{{ ($i % 5) + 1 }}">
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
            <a wire:navigate href="{{ localized_route('connect') }}" class="theme-btn btn-style border" style="color:#fff;border-color:rgba(247,244,238,.5)">
                <span>{{ site_trans('buttons.book_your_stay') }}</span>
            </a>
        </div>
    </div>
</section>
</div>
