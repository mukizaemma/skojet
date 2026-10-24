@php
    $night = hotel_nightly_offer($room, $checkin ?? null, $checkout ?? null, $setting ?? null);
    $variant = $variant ?? 'inline';
@endphp
<span class="skojet-offer skojet-offer--{{ $variant }}">
    @if($night['has_discount'])
        <s>{{ hotel_price($night['list'], $setting ?? null) }}</s>
    @endif
    <span class="skojet-offer__now">{{ hotel_price($night['sale'], $setting ?? null) }}</span>
    @if($night['has_discount'] && $night['badge'] !== '')
        <em class="skojet-offer__badge">{{ $night['badge'] }}</em>
    @endif
</span>
