@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-1">{{ $booking->names }}</h4>
                <div class="text-muted">{{ $booking->nights() }} nights · {{ ucfirst(str_replace('_', ' ', $booking->deskState())) }} · {{ ucfirst($booking->payment_status) }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2 desk-no-print">
                <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.receipt', $booking) }}" target="_blank">Receipt</a>
                @if(! $booking->checked_in_at)
                    <button class="btn btn-success" type="submit" form="desk-stay" name="after" value="checkin">Check in</button>
                @elseif(! $booking->checked_out_at)
                    <form method="POST" action="{{ route('content-management.front-office.check-out', $booking) }}">@csrf<button class="btn btn-dark" type="submit">Check out</button></form>
                @endif
                @if($booking->status !== 'cancelled')
                    <form method="POST" action="{{ route('content-management.front-office.cancel', $booking) }}" onsubmit="return confirm('Cancel this reservation?')">@csrf<button class="btn btn-outline-danger" type="submit">Cancel booking</button></form>
                @endif
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <form id="desk-stay" method="POST" action="{{ route('content-management.front-office.update', $booking) }}" class="bg-white border rounded p-4">
                    @csrf
                    @include('content-management.front-office.partials.stay-fields')
                    <p class="text-muted small mt-3 mb-0">Saving keeps the booked total unless the suite, dates, guests, board, or occupancy change. Those changes use the suite’s room rates.</p>
                    <button class="btn btn-dark mt-3" type="submit">Save details</button>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="desk-summary mb-3">
                    <h6 class="mb-3">Booking summary</h6>
                    <dl class="row mb-0">
                        <dt class="col-7">Room total</dt>
                        <dd class="col-5">{{ hotel_price($booking->total_amount, $setting ?? null) }}</dd>
                        @if((float) $booking->discount_amount > 0)
                            <dt class="col-7">Discount</dt>
                            <dd class="col-5">{{ hotel_price($booking->discount_amount, $setting ?? null) }}</dd>
                        @endif
                        <dt class="col-7">Paid</dt>
                        <dd class="col-5">{{ hotel_price($booking->paid_amount, $setting ?? null) }}</dd>
                        <dt class="col-7">Outstanding</dt>
                        <dd class="col-5 fw-bold">{{ hotel_price($booking->balance_amount, $setting ?? null) }}</dd>
                    </dl>
                    @if($booking->discount_label)
                        <p class="small text-muted mb-0">{{ $booking->discount_label }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('content-management.front-office.pay', $booking) }}" class="bg-white border rounded p-3">
                    @csrf
                    <h6>Record payment</h6>
                    <div class="mb-2">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            @foreach(\App\Models\BookingPayment::METHODS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" maxlength="255">
                    </div>
                    <button class="btn btn-dark w-100" type="submit">Record payment</button>
                </form>
                @if($booking->payments->isNotEmpty())
                    <ul class="list-group mt-3">
                        @foreach($booking->payments as $payment)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $payment->methodLabel() }} · {{ $payment->created_at->format('d M H:i') }}</span>
                                <strong>{{ hotel_price($payment->amount, $setting ?? null) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
