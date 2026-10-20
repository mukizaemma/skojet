@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @php $invoice = hotel_invoice($setting); @endphp
        <div class="desk-no-print mb-3">
            <button class="btn btn-dark" type="button" onclick="window.print()">Print invoice</button>
            <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.show', $booking) }}">Back</a>
        </div>
        <div class="bg-white border rounded p-4" style="max-width: 720px;">
            <h4 class="mb-1">{{ $invoice['name'] }}</h4>
            @if($invoice['address'] !== '')
                <p class="mb-1">{{ $invoice['address'] }}</p>
            @endif
            @if($invoice['phone'] !== '' || $invoice['email'] !== '')
                <p class="mb-1">{{ collect([$invoice['phone'], $invoice['email']])->filter()->implode(' · ') }}</p>
            @endif
            @if($invoice['tax_id'] !== '')
                <p class="mb-1">Tax ID {{ $invoice['tax_id'] }}</p>
            @endif
            <p class="text-muted">Invoice · Reservation {{ $booking->id }}</p>
            <p class="mb-1"><strong>{{ $booking->names }}</strong></p>
            <p class="mb-1">{{ $booking->stayRoom()?->title }}</p>
            <p class="mb-1">{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }} · {{ $booking->nights() }} nights</p>
            <p>{{ \App\Models\Room::BOARD_PLANS[$booking->board] ?? 'Standard rate' }}{{ $booking->occupancy ? ' · '.ucfirst($booking->occupancy) : '' }}</p>
            <table class="table">
                <tbody>
                    <tr><td>Room</td><td class="text-end">{{ hotel_price($booking->total_amount, $setting, $booking->currencyKey()) }}</td></tr>
                    @foreach($booking->charges as $charge)
                        <tr>
                            <td>{{ $charge->categoryLabel() }} · {{ $charge->name }}@if($charge->included) (included in the room)@endif</td>
                            <td class="text-end">{{ $charge->included ? 'Included' : hotel_price($charge->line_total, $setting, $booking->currencyKey()) }}</td>
                        </tr>
                    @endforeach
                    @if((float) ($booking->waived_amount ?? 0) >= 0.5)
                        <tr><td>Complimentary</td><td class="text-end">{{ hotel_price($booking->waived_amount, $setting, $booking->currencyKey()) }}</td></tr>
                    @endif
                    <tr><td>Paid</td><td class="text-end">{{ hotel_price($booking->paid_amount, $setting, $booking->currencyKey()) }}</td></tr>
                    <tr><td>Outstanding</td><td class="text-end">{{ hotel_price($booking->balance_amount, $setting, $booking->currencyKey()) }}</td></tr>
                </tbody>
            </table>
            <h6>Payments</h6>
            <ul class="list-unstyled">
                @forelse($booking->payments as $payment)
                    <li>{{ $payment->created_at->format('d M Y H:i') }} · {{ $payment->methodLabel() }} · {{ hotel_price($payment->amount, $setting, $booking->currencyKey()) }}</li>
                @empty
                    <li class="text-muted">No payments recorded.</li>
                @endforelse
            </ul>
            @if($invoice['footer'] !== '')
                <p class="mt-4 mb-0">{{ $invoice['footer'] }}</p>
            @endif
        </div>
    </div>
</div>
@if(request()->boolean('print'))
<script>window.addEventListener('load', function () { window.print(); });</script>
@endif
@endsection
