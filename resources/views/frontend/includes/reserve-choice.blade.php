@php $mode = $mode ?? 'room'; @endphp
<nav class="reserve-choice" aria-label="{{ site_trans('dining.reserve_choice_label') }}">
    <p>{{ site_trans('dining.reserve_choice_lead') }}</p>
    <div>
        <a href="{{ localized_route('connect') }}" class="{{ $mode === 'room' ? 'is-current' : '' }}">{{ site_trans('dining.reserve_room') }}</a>
        <a href="{{ localized_route('dining.reserve') }}" class="{{ $mode === 'restaurant' ? 'is-current' : '' }}">{{ site_trans('dining.reserve_restaurant') }}</a>
    </div>
</nav>
