@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        <div class="desk-no-print mb-3">
            <button class="btn btn-dark" type="button" onclick="window.print()">Print receipt</button>
            <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.show', $booking) }}">Back</a>
        </div>
        <div class="bg-white border rounded p-4" style="max-width: 720px;">
            <h4 class="mb-1">{{ $setting->company ?? 'Skojet Boutique Hotel' }}</h4>
            <p class="text-muted">Receipt · Reservation {{ $booking->id }}</p>
            <p class="mb-1"><strong>{{ $booking->names }}</strong></p>
            <p class="mb-1">{{ $booking->stayRoom()?->title }}</p>
            <p class="mb-1">{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }} · {{ $booking->nights() }} nights</p>
            <p>{{ \App\Models\Room::BOARD_PLANS[$booking->board] ?? 'Standard rate' }}{{ $booking->occupancy ? ' · '.ucfirst($booking->occupancy) : '' }}</p>
            <table class="table">
                <tbody>
                    <tr><td>Room total</td><td class="text-end">{{ hotel_price($booking->total_amount, $setting) }}</td></tr>
                    <tr><td>Paid</td><td class="text-end">{{ hotel_price($booking->paid_amount, $setting) }}</td></tr>
                    <tr><td>Outstanding</td><td class="text-end">{{ hotel_price($booking->balance_amount, $setting) }}</td></tr>
                </tbody>
            </table>
            <h6>Payments</h6>
            <ul class="list-unstyled">
                @forelse($booking->payments as $payment)
                    <li>{{ $payment->created_at->format('d M Y H:i') }} · {{ $payment->methodLabel() }} · {{ hotel_price($payment->amount, $setting) }}</li>
                @empty
                    <li class="text-muted">No payments recorded.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
</div>
@endsection
