<div class="public-livewire-page kibeho-room-detail skojet-room-detail">

@php
    $allImages = collect();
    if ($room->cover_image) {
        $allImages->push((object) ['image' => $room->cover_image, 'is_cover' => true]);
    }
    if ($images && count($images) > 0) {
        foreach ($images as $img) {
            $allImages->push((object) ['image' => $img->image, 'is_cover' => false]);
        }
    }
    $hasAmenities = isset($amenities) && $amenities && $amenities->count() > 0;
    $fallbackImage = asset('storage/' . ($room->cover_image ?? 'rooms/default.jpg'));
@endphp

<nav class="room-detail-breadcrumb" aria-label="Breadcrumb">
    <div class="container">
        <a wire:navigate href="{{ localized_route('rooms') }}" class="room-detail-breadcrumb__link">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            {{ site_trans('room.back_to_rooms') }}
        </a>
    </div>
</nav>

<section class="room-detail-showcase rts__section section__padding">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-lg-7 room-gallery-col">
                <div class="room-detail-gallery">
                    @if($allImages->count() > 0)
                        <div class="room-detail-gallery__main">
                            <img id="roomMainImage"
                                 src="{{ asset('storage/' . $allImages->first()->image) }}"
                                 alt="{{ $room->title }}"
                                 loading="eager"
                                 class="room-detail-gallery__main-img">
                        </div>
                        @if($allImages->count() > 1)
                            <div class="room-detail-gallery__thumbs" role="list">
                                @foreach($allImages as $key => $img)
                                    <button type="button"
                                            class="room-detail-gallery__thumb {{ $key === 0 ? 'is-active' : '' }}"
                                            data-image="{{ asset('storage/' . $img->image) }}"
                                            aria-label="View image {{ $key + 1 }}"
                                            aria-pressed="{{ $key === 0 ? 'true' : 'false' }}">
                                        <img src="{{ asset('storage/' . $img->image) }}"
                                             alt=""
                                             loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="room-detail-gallery__main">
                            <img src="{{ $fallbackImage }}"
                                 alt="{{ $room->title }}"
                                 loading="eager"
                                 class="room-detail-gallery__main-img">
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-5 room-details-col">
                <aside class="room-detail-aside">
                    <p class="room-detail-aside__eyebrow">{{ site_trans('room.eyebrow') }}</p>
                    <h1 class="room-detail-aside__title">{{ $room->title }}</h1>

                    <div class="room-detail-price-card rates-panel">
                        @if($room->bed_type && isset(\App\Models\Room::BED_TYPES[$room->bed_type]))
                            <span class="room-detail-bed">{{ site_trans('room.bed_'.$room->bed_type) }}</span>
                        @endif
                        <span class="room-detail-price-card__label">{{ site_trans('room.from') }}</span>
                        <div class="room-detail-price-card__amount">
                            @include('frontend.includes.offer-price', ['room' => $room, 'setting' => $setting, 'variant' => 'detail'])
                            <span class="room-detail-price-card__unit">{{ site_trans('room.per_night') }}</span>
                        </div>
                        <p class="room-detail-price-card__note">{{ site_trans('room.price_note') }}</p>
                    </div>

                    <div class="room-detail-book">
                        <p class="room-detail-book__lead">{{ site_trans('room.book_lead') }}</p>
                        @if(hotel_book_now_is_external($setting ?? null))
                            @include('frontend.includes.reservation-link', [
                                'style' => 'fill',
                                'class' => 'room-detail-book__btn w-100 text-center',
                                'icon' => 'fa-solid fa-calendar-check',
                            ])
                        @else
                            <a wire:navigate
                               href="{{ localized_route('connect', ['room' => $room->id]) }}"
                               class="theme-btn btn-style fill room-detail-book__btn w-100 text-center">
                                <i class="fa-solid fa-calendar-check me-2" aria-hidden="true"></i>
                                <span>{{ hotel_book_now_label() }}</span>
                            </a>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

@if($room->publicRatePlans(hotel_currency($setting ?? null)) !== [])
<section class="room-rate-board rts__section section__padding pt-0">
    <div class="container">
        <header class="room-rate-board__header">
            <h2>{{ site_trans('room.rates_title') }}</h2>
            <p>{{ site_trans('room.rates_lead') }}</p>
        </header>
        <div class="room-rate-board__grid">
            @foreach($room->publicRatePlans(hotel_currency($setting ?? null)) as $plan)
                <article class="room-rate-board__card">
                    <header class="room-rate-board__head">
                        <h3>{{ site_trans('room.board_'.$plan['key']) }}</h3>
                    </header>
                    <div class="room-rate-board__body">
                        @if($plan['discount_type'] === 'percent' && $plan['discount_value'])
                            <span class="room-rate-board__ribbon">{{ site_trans('room.off_percent', ['value' => rtrim(rtrim(number_format((float) $plan['discount_value'], 2, '.', ''), '0'), '.')]) }}</span>
                        @elseif($plan['discount_type'] === 'amount' && $plan['discount_value'])
                            <span class="room-rate-board__ribbon">{{ site_trans('room.off_amount', ['value' => hotel_price($plan['discount_value'], $setting)]) }}</span>
                        @endif
                        @php $packageOfferBadge = ''; @endphp
                        @foreach(['single', 'double'] as $occupancyKey)
                            @if($plan[$occupancyKey] !== null)
                                @php
                                    $packageQuote = app(\App\Services\RoomDiscountService::class)->quote(
                                        $room,
                                        now()->toDateString(),
                                        now()->addDay()->toDateString(),
                                        $occupancyKey === 'single' ? 1 : 2,
                                        0,
                                        1,
                                        0,
                                        $plan['key'],
                                        $occupancyKey
                                    );
                                    $packageFinal = (float) (($packageQuote['has_discount'] ?? false) ? $packageQuote['sale_nightly'] : $plan[$occupancyKey.'_sale']);
                                    $packageStrike = $packageFinal + 0.5 < (float) $plan[$occupancyKey]
                                        ? (float) $plan[$occupancyKey]
                                        : null;
                                    if ($packageOfferBadge === '' && ($packageQuote['has_discount'] ?? false)) {
                                        $packageOfferBadge = (string) ($packageQuote['badge'] ?? '');
                                    }
                                @endphp
                                <div class="room-rate-board__row">
                                    <span>{{ site_trans('room.occ_'.$occupancyKey.'_long') }}</span>
                                    <strong>
                                        @if($packageStrike !== null)
                                            <s>{{ hotel_price($packageStrike, $setting) }}</s>
                                        @endif
                                        {{ hotel_price($packageFinal, $setting) }}
                                    </strong>
                                </div>
                            @endif
                        @endforeach
                        @if($packageOfferBadge !== '')
                            <em class="room-rate-board__offer">{{ $packageOfferBadge }}</em>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(filled(strip_tags((string) $room->description)))
<section class="room-detail-about rts__section section__padding pt-0">
    <div class="container">
        <div class="room-detail-about__card info-panel">
            <h2 class="room-detail-about__title">{{ site_trans('room.about') }}</h2>
            <div class="room-detail-about__prose content-richtext">
                {!! $room->description !!}
            </div>
        </div>
    </div>
</section>
@endif

@if($hasAmenities)
<section class="room-detail-amenities rts__section section__padding pt-0">
    <div class="container">
        <h2 class="room-detail-amenities__heading">{{ site_trans('room.amenities') }}</h2>
        <ul class="room-detail-amenities__grid">
            @foreach($amenities as $amenity)
                <li class="room-detail-amenities__item">
                    @if($amenity->icon)
                        <span class="room-detail-amenities__icon" aria-hidden="true"><i class="fa {{ $amenity->icon }}"></i></span>
                    @else
                        <span class="room-detail-amenities__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    @endif
                    <span>{{ $amenity->title }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if($allRooms && $allRooms->count() > 0)
<section class="room-detail-related rts__section section__padding">
    <div class="container">
        <header class="room-detail-related__header text-center">
            <h2 class="room-detail-related__title">{{ site_trans('room.other_rooms') }}</h2>
            <p class="room-detail-related__lead">{{ site_trans('room.other_rooms_lead') }}</p>
        </header>
        <div class="row g-4">
            @foreach ($allRooms as $similarRoom)
                @include('frontend.includes.room-listing-card', [
                    'room' => $similarRoom,
                    'colClass' => 'col-lg-4 col-md-6',
                ])
            @endforeach
        </div>
    </div>
</section>
@endif

<script>
(function () {
    function initRoomGallery() {
        var mainImage = document.getElementById('roomMainImage');
        var thumbs = document.querySelectorAll('.room-detail-gallery__thumb');
        if (!mainImage || !thumbs.length) {
            return;
        }

        thumbs.forEach(function (thumb) {
            if (thumb.dataset.bound === '1') {
                return;
            }
            thumb.dataset.bound = '1';
            thumb.addEventListener('click', function () {
                var newSrc = this.getAttribute('data-image');
                thumbs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('is-active');
                this.setAttribute('aria-pressed', 'true');
                mainImage.style.opacity = '0';
                setTimeout(function () {
                    mainImage.src = newSrc;
                    mainImage.style.opacity = '1';
                }, 180);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initRoomGallery);
    document.addEventListener('livewire:navigated', initRoomGallery);
})();
</script>
</div>
