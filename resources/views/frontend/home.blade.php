<div class="livewire-home-page skojet-home">
{{-- 1. Full-screen cinematic hero --}}
@include('frontend.includes.slides')

{{-- 2. Invitation after the hero --}}
<section class="rts__section skojet-why section__padding" id="why-skojet" aria-labelledby="why-skojet-heading">
    <div class="container">
        <div class="skojet-why__layout">
            <div class="skojet-why__story">
                <span class="skojet-intro__eyebrow">{{ site_trans('home.why_eyebrow') }}</span>
                <h2 id="why-skojet-heading" class="skojet-why__title">{{ site_trans('home.why_title') }}</h2>
                <p class="skojet-why__lead">{{ site_trans('home.why_lead') }}</p>
                <p class="skojet-why__note">{{ site_trans('home.invite_note') }}</p>
                <a href="#signature-suite" data-no-spa-navigate class="skojet-why__link" onclick="var el=document.getElementById('signature-suite'); if(el){el.scrollIntoView({behavior:'smooth',block:'start'});} return false;">
                    <span>{{ site_trans('home.invite_cta') }}</span>
                    <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                </a>
            </div>
            <div class="skojet-stats" data-skojet-counters>
                @foreach ([
                    ['value' => '4', 'count' => true, 'label' => site_trans('home.stat_suites')],
                    ['value' => site_trans('home.stat_hospitality_mark'), 'count' => false, 'label' => site_trans('home.stat_hospitality')],
                    ['value' => site_trans('home.stat_volcano_mark'), 'count' => false, 'label' => site_trans('home.stat_volcano')],
                    ['value' => site_trans('home.stat_transfers_mark'), 'count' => false, 'label' => site_trans('home.stat_transfers')],
                ] as $i => $stat)
                <div class="skojet-stat" style="animation-delay: {{ $i * 0.08 }}s">
                    @if($stat['count'])
                    <span class="skojet-stat__value" data-count="{{ $stat['value'] }}" data-suffix="">{{ $stat['value'] }}</span>
                    @else
                    <span class="skojet-stat__value skojet-stat__value--text">{{ $stat['value'] }}</span>
                    @endif
                    <span class="skojet-stat__label">{{ $stat['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- 3. Suites — first image left, second image right --}}
@php
    $featuredSuites = ($rooms ?? collect())->take(2)->values();
@endphp
@foreach ($featuredSuites as $suiteIndex => $featuredSuite)
@php
    $suitePlain = trim(preg_replace('/\s+/', ' ', strip_tags($featuredSuite->description ?? '')));
    $suiteBlurb = mb_strlen($suitePlain) > 24
        ? \Illuminate\Support\Str::words($suitePlain, 42, '…')
        : site_trans('home.signature_fallback');
@endphp
<section class="skojet-signature {{ $suiteIndex === 1 ? 'skojet-signature--flip' : '' }}" @if($suiteIndex === 0) id="signature-suite" @endif aria-labelledby="signature-suite-heading-{{ $featuredSuite->id }}">
    <div class="skojet-signature__grid">
        <div class="skojet-signature__media">
            <img src="{{ asset('storage/' . ($featuredSuite->cover_image ?? 'rooms/default.jpg')) }}"
                 alt="{{ $featuredSuite->title }}"
                 loading="lazy"
                 width="1200"
                 height="900">
        </div>
        <div class="skojet-signature__panel">
            <span class="skojet-intro__eyebrow">{{ $suiteIndex === 0 ? site_trans('home.signature_eyebrow') : site_trans('home.also_eyebrow') }}</span>
            <h2 id="signature-suite-heading-{{ $featuredSuite->id }}" class="skojet-intro__title">{{ $featuredSuite->title }}</h2>
            <p class="skojet-intro__lead">{{ $suiteBlurb }}</p>
            <div class="skojet-signature__meta">
                <span>{{ site_trans('home.signature_meta_lounge') }}</span>
                <span>{{ site_trans('home.signature_meta_bath') }}</span>
                <span>{{ site_trans('home.signature_meta_balcony') }}</span>
            </div>
            <div class="skojet-signature__actions">
                <a wire:navigate href="{{ localized_route('room', ['slug' => $featuredSuite->slug]) }}" class="theme-btn btn-style fill">
                    <span>{{ site_trans('home.view_suite') }}</span>
                </a>
                @if($suiteIndex === 0)
                <a wire:navigate href="{{ localized_route('rooms') }}" class="theme-btn btn-style border skojet-signature__ghost">
                    <span>{{ site_trans('home.explore_all_suites') }}</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</section>
@endforeach

{{-- 4. Homepage gallery — first 6 images, 3 columns --}}
@if(($homeGallery ?? collect())->isNotEmpty())
<section class="rts__section section__padding skojet-home-gallery" id="home-gallery" aria-labelledby="home-gallery-heading">
    <div class="container">
        <header class="skojet-intro">
            <span class="skojet-intro__eyebrow">{{ site_trans('home.gallery_eyebrow') }}</span>
            <h2 id="home-gallery-heading" class="skojet-intro__title">{{ site_trans('home.experiences_title') }}</h2>
        </header>
        <div class="skojet-home-gallery__grid">
            @foreach($homeGallery as $image)
                @continue($image->publicImageUrl() === '')
                <a wire:navigate href="{{ localized_route('gallery') }}" class="skojet-home-gallery__item">
                    <img src="{{ $image->publicImageUrl() }}" alt="{{ $image->caption ?: site_trans('home.gallery_eyebrow') }}" decoding="async">
                </a>
            @endforeach
        </div>
        <div class="text-center mt-4">
            <a wire:navigate href="{{ localized_route('gallery') }}" class="theme-btn btn-style border">
                <span>{{ site_trans('home.gallery_view') }}</span>
            </a>
        </div>
    </div>
</section>
@endif

{{-- 5. Location — Kinigi / Musanze / Volcanoes --}}
<section class="rts__section skojet-location section__padding" id="location" aria-labelledby="location-heading">
    <div class="container">
        <div class="skojet-location__grid">
            <div>
                <header class="skojet-intro" style="margin:0 0 0;text-align:left;max-width:none">
                    <span class="skojet-intro__eyebrow">{{ site_trans('home.location_eyebrow') }}</span>
                    <h2 id="location-heading" class="skojet-intro__title" style="text-align:left">{{ site_trans('home.location_title') }}</h2>
                    <p class="skojet-intro__lead" style="text-align:left">{{ site_trans('home.location_lead') }}</p>
                </header>
                <ul class="skojet-location__pins">
                    <li>
                        <i class="fa-solid fa-mountain" aria-hidden="true"></i>
                        <div>
                            <strong>{{ site_trans('home.pin_volcanoes_title') }}</strong>
                            <span>{{ site_trans('home.pin_volcanoes_text') }}</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-passport" aria-hidden="true"></i>
                        <div>
                            <strong>{{ site_trans('home.pin_rdb_title') }}</strong>
                            <span>{{ site_trans('home.pin_rdb_text') }}</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-tree" aria-hidden="true"></i>
                        <div>
                            <strong>{{ site_trans('home.pin_kinigi_title') }}</strong>
                            <span>{{ site_trans('home.pin_kinigi_text') }}</span>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="skojet-location__aside">
                @php
                    $restaurantCoverUrl = null;
                    $restaurantCoverAlt = site_trans('nav.dining');
                    if (isset($restaurant)) {
                        if (filled($restaurant->image)) {
                            $restaurantCoverUrl = asset('storage/images/restaurant/' . $restaurant->image);
                        } else {
                            $restaurantShot = $restaurant->relationLoaded('images') ? $restaurant->images->first() : null;
                            if ($restaurantShot && filled($restaurantShot->image)) {
                                $restaurantCoverUrl = asset('storage/images/restaurant/' . $restaurantShot->image);
                            }
                        }
                    }
                    if (! $restaurantCoverUrl && isset($slides)) {
                        $diningSlide = $slides->first(function ($slide) {
                            return str_contains((string) ($slide->image ?? ''), 'NpwOE7XzRxgMY7ynzurS4KaHuqI39vTabd0xMQya');
                        });
                        if ($diningSlide && filled($diningSlide->image)) {
                            $restaurantCoverUrl = asset('storage/' . $diningSlide->image);
                        }
                    }
                @endphp
                @if($restaurantCoverUrl)
                <a wire:navigate href="{{ localized_route('dining') }}" class="skojet-location__photo">
                    <img src="{{ $restaurantCoverUrl }}" alt="{{ $restaurantCoverAlt }}" loading="lazy" width="960" height="640">
                    <span>{{ $restaurantCoverAlt }}</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</section>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-skojet-counters]');
    if (!root || !('IntersectionObserver' in window)) return;

    var animated = false;
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting || animated) return;
            animated = true;
            root.querySelectorAll('[data-count]').forEach(function (el) {
                var target = parseInt(el.getAttribute('data-count'), 10) || 0;
                var suffix = el.getAttribute('data-suffix') || '';
                var start = 0;
                var duration = 1200;
                var startTime = null;
                function step(ts) {
                    if (!startTime) startTime = ts;
                    var progress = Math.min((ts - startTime) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.round(start + (target - start) * eased) + suffix;
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
            observer.disconnect();
        });
    }, { threshold: 0.35 });
    observer.observe(root);
});
</script>
@endpush
