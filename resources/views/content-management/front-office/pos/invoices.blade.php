@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos-nav">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="pos-head">
            <h4>Invoices</h4>
            @include('content-management.front-office.partials.pos-nav', ['current' => 'invoices'])
        </div>
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
            <div class="bg-white border rounded p-3 text-muted">Invoices appear after the dining tables are created. Run the database migration, then open this page again.</div>
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
                    <div class="col-12"><div class="bg-white border rounded p-3 text-muted">No bills in this period.</div></div>
                @endforelse
            </div>
            <div class="bg-white border rounded">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Bill</th><th>When</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Payment</th><th></th></tr></thead>
                    <tbody>
                        @forelse($visits as $visit)
                            @php $open = $visit->accessGranted(); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('content-management.restaurant-reservations.show', $visit) }}">{{ $visit->names }}</a>
                                    <div class="text-muted small">{{ $visit->reference }}</div>
                                </td>
                                <td>{{ optional($visit->reserved_for)->format('d M Y · H:i') }}</td>
                                <td class="text-end">{{ $visit->money((float) $visit->total_amount) }}</td>
                                <td class="text-end">{{ $visit->money((float) $visit->paid_amount) }}</td>
                                <td class="text-end">{{ $visit->money(max(0, (float) $visit->total_amount - (float) $visit->paid_amount)) }}</td>
                                <td>
                                    <span style="display:inline-block;padding:0.15rem 0.55rem;border-radius:999px;background:{{ $open ? '#1f7a3a' : '#e2b100' }};color:{{ $open ? '#fff' : '#1a1a1a' }};">{{ $open ? 'Paid' : 'Not paid' }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('content-management.restaurant-reservations.show', $visit) }}">View bill</a>
                                    <a class="ms-2" href="{{ route('content-management.restaurant-reservations.show', $visit) }}?print=1" target="_blank" rel="noopener">Print</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No bills in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
