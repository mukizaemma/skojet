@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <a class="desk-pos__back" href="{{ route('content-management.front-office.pos') }}">POS</a>
        <h4 class="mb-1">Sales</h4>
        <p class="text-muted mb-3">Dining bills for these dates, and restaurant items posted onto a room bill.</p>
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
        @if(! $ready)
            <div class="bg-white border rounded p-3 text-muted">Restaurant sales appear after the dining tables are created. Run the database migration, then open this page again.</div>
        @else
            <div class="row g-3 mb-4">
                @forelse($tiers as $tier)
                    <div class="col-md-6">
                        <div class="bg-white border rounded p-3 h-100">
                            <div class="text-muted small">{{ $tier['label'] }}</div>
                            <div class="fs-4">{{ restaurant_money($tier['total'], $tier['tier']) }}</div>
                            <div class="text-muted small">{{ $tier['count'] }} {{ $tier['count'] === 1 ? 'bill' : 'bills' }} · paid {{ restaurant_money($tier['paid'], $tier['tier']) }} · due {{ restaurant_money($tier['balance'], $tier['tier']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="bg-white border rounded p-3 text-muted">No dining bills in this period.</div></div>
                @endforelse
            </div>
            <h6 class="mb-2">Dining bills</h6>
            <div class="bg-white border rounded mb-4">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Guest</th><th>When</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th></th></tr></thead>
                    <tbody>
                        @forelse($visits as $visit)
                            <tr>
                                <td>{{ $visit->names }}<div class="text-muted small">{{ $visit->reference }}</div></td>
                                <td>{{ optional($visit->reserved_for)->format('d M Y · H:i') }}</td>
                                <td class="text-end">{{ $visit->money((float) $visit->total_amount) }}</td>
                                <td class="text-end">{{ $visit->money((float) $visit->paid_amount) }}</td>
                                <td class="text-end">{{ $visit->money(max(0, (float) $visit->total_amount - (float) $visit->paid_amount)) }}</td>
                                <td class="text-end"><a href="{{ route('content-management.restaurant-reservations.show', $visit) }}">Open bill</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No dining bills in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <h6 class="mb-2">Posted to a room bill</h6>
            <div class="bg-white border rounded">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Guest</th><th>Item</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @forelse($roomCharges as $charge)
                            <tr>
                                <td>
                                    @if($charge->booking)
                                        <a href="{{ route('content-management.front-office.show', $charge->booking) }}">{{ $charge->booking->names }}</a>
                                    @endif
                                </td>
                                <td>{{ $charge->name }}</td>
                                <td class="text-end">{{ hotel_price($charge->line_total, $setting ?? null, $charge->booking?->currencyKey() ?? 'usd') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted p-3">No restaurant items were posted to a room in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
