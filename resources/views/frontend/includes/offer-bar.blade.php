@php
    $todayOffers = hotel_offers_today();
@endphp
@if($todayOffers->isNotEmpty())
    <div class="skojet-offer-bar" role="region" aria-label="{{ site_trans('offers.region') }}">
        <div class="container skojet-offer-bar__row">
            @foreach($todayOffers->take(2) as $offer)
                <p>
                    <span class="skojet-offer-bar__kicker">{{ site_trans('offers.mark') }}</span>
                    <strong>{{ $offer->title }}</strong>
                    <em>{{ hotel_offer_badge($offer, $setting ?? null) }}</em>
                    <span>{{ $offer->room?->title ?: site_trans('offers.all_suites') }}</span>
                    <span class="skojet-offer-bar__until">{{ site_trans('offers.until', ['date' => $offer->ends_on?->format('M j')]) }}</span>
                </p>
            @endforeach
        </div>
    </div>
@endif
