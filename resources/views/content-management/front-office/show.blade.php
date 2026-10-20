@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-1">{{ $booking->names }}</h4>
                <div class="text-muted">{{ $booking->nights() }} nights · {{ ucfirst(str_replace('_', ' ', $booking->deskState())) }} · {{ (float) ($booking->waived_amount ?? 0) > 0 && (float) $booking->paid_amount <= 0 && (float) $booking->balance_amount <= 0 ? 'Complimentary' : ucfirst($booking->payment_status) }} · {{ $booking->channelLabel() }} · {{ $booking->sourceLabel() }}</div>
                @if($booking->booked_checkin_date && optional($booking->booked_checkin_date)->toDateString() !== optional($booking->checkin_date)->toDateString())
                    <div class="text-muted">Arrival was {{ $booking->booked_checkin_date->format('d M Y') }} and is now {{ optional($booking->checkin_date)->format('d M Y') }}{{ $booking->checkin_note ? ' · '.$booking->checkin_note : '' }}</div>
                @endif
                @if($booking->booked_checkout_date && optional($booking->booked_checkout_date)->toDateString() !== optional($booking->checkout_date)->toDateString())
                    <div class="text-muted">Departure was {{ $booking->booked_checkout_date->format('d M Y') }} and is now {{ optional($booking->checkout_date)->format('d M Y') }}{{ $booking->checkout_note ? ' · '.$booking->checkout_note : '' }}</div>
                @endif
            </div>
            @if($booking->status !== 'cancelled')
                <a class="btn btn-outline-danger desk-no-print" href="{{ route('content-management.front-office.cancel.confirm', $booking) }}">Cancel booking</a>
            @endif
        </div>
        <div class="desk-book">
            <form id="desk-stay" method="POST" action="{{ route('content-management.front-office.update', $booking) }}" class="desk-book__form bg-white border rounded p-4">
                @csrf
                @include('content-management.front-office.partials.stay-fields')
                <p class="text-muted small mt-3 mb-0">Saving keeps the booked total unless the suite, dates, guests, board, or occupancy change.</p>
            </form>
            <div class="desk-book__side">
                @include('content-management.front-office.partials.cart')
                @include('content-management.front-office.partials.charges')
                @include('content-management.front-office.partials.payment-box')
                @if($booking->payments->isNotEmpty())
                    <ul class="list-group mt-3">
                        @foreach($booking->payments as $payment)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $payment->methodLabel() }} · {{ $payment->created_at->format('d M H:i') }}</span>
                                <strong>{{ hotel_price($payment->amount, $setting ?? null, $booking->currencyKey()) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @include('content-management.front-office.partials.stay-next')
            </div>
        </div>
    </div>
</div>
@endsection
