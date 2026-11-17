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

@endphp

<section class="rts__section section__padding skojet-coffee-menu" aria-labelledby="coffee-menu-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.menu_eyebrow') }}</span>
            <h2 id="coffee-menu-heading" class="skojet-intro__title">{{ site_trans('dining.menu_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.menu_lead') }}</p>
        </header>
        @forelse(($menu ?? []) as $category => $items)
            <section class="dining-menu" aria-labelledby="menu-{{ $category }}">
                <h3 id="menu-{{ $category }}">{{ site_trans('dining.menu_'.$category) }}</h3>
                <ul>
                    @foreach($items as $item)
                        <li>
                            <div>
                                <strong>{{ $item->name }}</strong>
                                @if(filled($item->description))
                                    <span>{{ $item->description }}</span>
                                @endif
                            </div>
                            <em>{{ restaurant_money($item->price_international, 'international') }}</em>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="dining-reserve__empty">{{ site_trans('dining.menu_empty') }}</p>
        @endforelse
    </div>
</section>

{{-- Gallery highlights --}}
@if($highlightImages->isNotEmpty())
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
        @endif
    </div>
</section>
@endif

{{-- CTA --}}
<section class="rts__section section__padding skojet-book-band" aria-labelledby="coffee-cta-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('dining.eyebrow') }}</span>
            <h2 id="coffee-cta-heading" class="skojet-intro__title">{{ site_trans('dining.cta_title') }}</h2>
            <p class="skojet-intro__lead">{{ site_trans('dining.cta_lead') }}</p>
        </header>
        <div class="skojet-book-band__actions">
            <a wire:navigate href="{{ localized_route('dining.reserve') }}" class="theme-btn btn-style fill">
                <span>{{ site_trans('dining.cta_restaurant') }}</span>
            </a>
            <a wire:navigate href="{{ localized_route('connect') }}" class="theme-btn btn-style border">
                <span>{{ site_trans('dining.cta_room') }}</span>
            </a>
        </div>
    </div>
</section>
</div>
