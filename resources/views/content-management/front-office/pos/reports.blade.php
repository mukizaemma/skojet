@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos-nav">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="pos-head">
            <h4>POS Reports</h4>
            @include('content-management.front-office.partials.pos-nav', ['current' => 'reports'])
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
            <div class="bg-white border rounded p-3 text-muted">Restaurant reports appear after the dining tables are created. Run the database migration, then open this page again.</div>
        @else
            <div class="row g-3 mb-4">
                @foreach([
                    ['Reservations', $counts['reservations']],
                    ['Paid', $counts['paid']],
                    ['Not paid', $counts['unpaid']],
                    ['Cancelled', $counts['cancelled']],
                ] as [$label, $value])
                    <div class="col-sm-6 col-xl-3">
                        <div class="bg-white border rounded p-3 h-100">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-4">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row g-3 mb-4">
                @forelse($tiers as $tier)
                    <div class="col-md-6">
                        <div class="bg-white border rounded p-3 h-100">
                            <div class="text-muted small">{{ $tier['label'] }} sales</div>
                            <div class="fs-4">{{ restaurant_money($tier['total'], $tier['tier']) }}</div>
                            <div class="text-muted small">Paid {{ restaurant_money($tier['paid'], $tier['tier']) }} · due {{ restaurant_money($tier['balance'], $tier['tier']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="bg-white border rounded p-3 text-muted">No dining sales in this period.</div></div>
                @endforelse
            </div>
            <h6 class="mb-2">Sold by category</h6>
            <div class="bg-white border rounded">
                <table class="table mb-0">
                    <thead><tr><th>Category</th><th class="text-end">International</th><th class="text-end">Local</th></tr></thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td>{{ $category['label'] }}</td>
                                <td class="text-end">{{ restaurant_money($category['international'], 'international') }}</td>
                                <td class="text-end">{{ restaurant_money($category['local'], 'local') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
