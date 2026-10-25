@php
    $todayOffers = hotel_offers_today();
    $offerVariant = $variant ?? 'header';
@endphp
@if($todayOffers->isNotEmpty())
    @if($offerVariant === 'footer')
        <div class="skojet-footer__offers" role="region" aria-label="{{ site_trans('offers.region') }}">
            <p class="skojet-footer__label">{{ site_trans('offers.region') }}</p>
            @foreach($todayOffers as $offer)
                <p class="skojet-footer__offer">
                    <strong>{{ $offer->title }}</strong>
                    <span>
                        {{ hotel_offer_badge($offer, $setting ?? null) }}
                        · {{ $offer->room?->title ?: site_trans('offers.all_suites') }}
                        · {{ site_trans('offers.until', ['date' => $offer->ends_on?->format('M j, Y')]) }}
                    </span>
                </p>
            @endforeach
        </div>
    @else
        <div class="header__top__offer" role="region" aria-label="{{ site_trans('offers.region') }}">
            <p class="header__top__offer-line">
                @foreach($todayOffers->take(2) as $offer)
                    @if(!$loop->first)
                        <span class="header__top__offer-sep" aria-hidden="true">·</span>
                    @endif
                    <span class="header__top__offer-kicker">{{ site_trans('offers.mark') }}</span>
                    <strong>{{ $offer->title }}</strong>
                    <em>{{ hotel_offer_badge($offer, $setting ?? null) }}</em>
                    <span>{{ $offer->room?->title ?: site_trans('offers.all_suites') }}</span>
                    <span class="header__top__offer-until">{{ site_trans('offers.until', ['date' => $offer->ends_on?->format('M j')]) }}</span>
                @endforeach
            </p>
        </div>
    @endif
@endif
