@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <h4 class="mb-3">Reports</h4>
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-auto">
                <label class="form-label mb-1">From</label>
                <input type="date" name="start_date" value="{{ $start }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1">To</label>
                <input type="date" name="end_date" value="{{ $end }}" class="form-control">
            </div>
            <div class="col-auto"><button class="btn btn-dark">Update</button></div>
        </form>
        @foreach($summaries as $summary)
            <h6 class="mb-2">{{ $summary['currency'] === 'rwf' ? 'RWF' : 'USD' }}</h6>
            <div class="row g-3 mb-2">
                <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Room sales</div><div class="fs-4">{{ hotel_price($summary['total'], $setting ?? null, $summary['currency']) }}</div><div class="text-muted small">Stays checking in during this period, after any arrival or departure change.</div></div></div>
                <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Restaurant sales</div><div class="fs-4">{{ hotel_price($summary['restaurant'], $setting ?? null, $summary['currency']) }}</div><div class="text-muted small">Food, drink, and coffee posted in this period.</div></div></div>
                <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Services sales</div><div class="fs-4">{{ hotel_price($summary['services'], $setting ?? null, $summary['currency']) }}</div><div class="text-muted small">Laundry, massage, transport, and tour guide posted in this period.</div></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><div class="bg-white border rounded p-3"><div class="text-muted small">Received on these stays</div><div class="fs-4">{{ hotel_price($summary['paid'], $setting ?? null, $summary['currency']) }}</div></div></div>
                <div class="col-md-6"><div class="bg-white border rounded p-3"><div class="text-muted small">Outstanding on these stays</div><div class="fs-4">{{ hotel_price($summary['balance'], $setting ?? null, $summary['currency']) }}</div></div></div>
            </div>
        @endforeach
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="bg-white border rounded p-3 h-100">
                    <h6 class="mb-3">How reservations were made</h6>
                    @foreach(\App\Models\Booking::CHANNELS as $key => $label)
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>{{ $label }}</span>
                            <strong>{{ $channels[$key] ?? 0 }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-white border rounded p-3 h-100">
                    <h6 class="mb-3">Where guests came from</h6>
                    @foreach(\App\Models\Booking::SOURCES as $key => $label)
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>{{ $label }}</span>
                            <strong>{{ $sources[$key] ?? 0 }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <h6 class="mb-2">Extra charges posted in this period</h6>
        <div class="bg-white border rounded mb-4">
            <table class="table mb-0">
                <thead><tr><th>Guest</th><th>Item</th><th>Category</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @forelse($charges as $charge)
                        <tr>
                            <td>{{ $charge->booking?->names }}</td>
                            <td>{{ $charge->name }}@if($charge->included) <span class="text-muted">included in the room</span>@endif</td>
                            <td>{{ $charge->categoryLabel() }}</td>
                            <td class="text-end">{{ $charge->included ? 'Included' : hotel_price($charge->line_total, $setting ?? null, $charge->booking?->currencyKey() ?? 'usd') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted p-3">No extra charges in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h6 class="mb-2">Room stays</h6>
        <div class="bg-white border rounded">
            <table class="table mb-0">
                <thead><tr><th>Guest</th><th>Check in</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td><a href="{{ route('content-management.front-office.show', $booking) }}">{{ $booking->names }}</a></td>
                            <td>{{ optional($booking->checkin_date)->format('d M Y') }}</td>
                            <td class="text-end">{{ hotel_price($booking->total_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                            <td class="text-end">{{ hotel_price($booking->paid_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                            <td class="text-end">{{ hotel_price($booking->balance_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted p-3">No stays in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
