@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-inventory">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="desk-inventory__bar">
            <div>
                <h4 class="mb-1">Room inventory</h4>
                <p class="text-muted mb-0">{{ $start->format('d M') }} – {{ $dates->last()->format('d M Y') }}</p>
            </div>
            <form method="GET" class="desk-inventory__nav">
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.inventory', ['start' => $prev]) }}">Previous</a>
                <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control form-control-sm">
                <button class="btn btn-dark btn-sm" type="submit">View</button>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.inventory', ['start' => $next]) }}">Next</a>
            </form>
        </div>
        <div class="desk-inventory__legend">
            <span><i class="desk-swatch desk-swatch--open"></i> Open</span>
            <span><i class="desk-swatch desk-swatch--booked"></i> Booked</span>
            <span><i class="desk-swatch desk-swatch--closed"></i> Closed</span>
        </div>
        @forelse($blocks as $block)
            @php
                $openCount = $block['availability']->filter(fn ($state) => $state === 'open')->count();
                $bookedCount = $block['availability']->filter(fn ($state) => $state === 'booked')->count();
                $closedCount = $block['availability']->filter(fn ($state) => $state === 'closed')->count();
            @endphp
            <article class="desk-suite">
                <header class="desk-suite__head">
                    <h5 class="mb-0">{{ $block['room']->title }}</h5>
                    <p class="desk-suite__counts mb-0">
                        <span>{{ $openCount }} open</span>
                        @if($bookedCount > 0)<span>{{ $bookedCount }} booked</span>@endif
                        @if($closedCount > 0)<span>{{ $closedCount }} closed</span>@endif
                    </p>
                </header>
                <div class="desk-suite__days">
                    @foreach($dates as $index => $date)
                        @php $state = $block['availability'][$index]; @endphp
                        <div class="desk-day desk-day--{{ $state }} {{ $date->isToday() ? 'is-today' : '' }} {{ $date->isWeekend() ? 'is-weekend' : '' }}" title="{{ $date->format('D, d M Y') }} · {{ ucfirst($state) }}">
                            @if($loop->first || $date->day === 1)
                                <span class="desk-day__month">{{ $date->format('M') }}</span>
                            @else
                                <span class="desk-day__month">&nbsp;</span>
                            @endif
                            <span class="desk-day__dow">{{ $date->format('D') }}</span>
                            <span class="desk-day__num">{{ $date->format('d') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="desk-suite__rates">
                    @php
                        $pricedPlans = collect($block['plans'])->filter(fn ($plan) => $plan['single'] !== null || $plan['double'] !== null || $plan['single_rwf'] !== null || $plan['double_rwf'] !== null);
                    @endphp
                    @forelse($pricedPlans as $planKey => $plan)
                        <div class="desk-rate">
                            <div class="desk-rate__name">{{ \App\Models\Room::BOARD_PLANS[$planKey] ?? $planKey }}</div>
                            @foreach(['single' => 'Single', 'double' => 'Double'] as $key => $label)
                                @if($plan[$key.'_sale'] !== null || $plan[$key.'_sale_rwf'] !== null)
                                    <div class="desk-rate__row">
                                        <span>{{ $label }}</span>
                                        <strong>
                                            @if($plan[$key.'_sale'] !== null)
                                                @if($plan[$key.'_sale'] < $plan[$key])
                                                    <s class="desk-rate__rack">{{ hotel_price($plan[$key], $setting, 'usd') }}</s>
                                                @endif
                                                {{ hotel_price($plan[$key.'_sale'], $setting, 'usd') }}
                                            @endif
                                            @if($plan[$key.'_sale_rwf'] !== null)
                                                <span class="d-block">{{ hotel_price($plan[$key.'_sale_rwf'], $setting, 'rwf') }}</span>
                                            @endif
                                        </strong>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @empty
                        <div class="desk-rate">
                            <div class="desk-rate__name">Standard rate</div>
                            <div class="desk-rate__row">
                                <span>Nightly</span>
                                <strong>
                                    {{ hotel_price($block['room']->price, $setting, 'usd') }}
                                    @if($block['room']->price_rwf !== null)
                                        <span class="d-block">{{ hotel_price($block['room']->price_rwf, $setting, 'rwf') }}</span>
                                    @endif
                                </strong>
                            </div>
                        </div>
                    @endforelse
                </div>
            </article>
        @empty
            <p class="text-muted">No active suites.</p>
        @endforelse
    </div>
</div>
@endsection
