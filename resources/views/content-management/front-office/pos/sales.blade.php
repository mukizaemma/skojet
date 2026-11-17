@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <a class="desk-pos__back" href="{{ route('content-management.front-office.pos') }}">POS</a>
        <h4 class="mb-1">Sales</h4>
        <p class="text-muted mb-3">Open a restaurant bill, then add items, confirm the payment, and print it.</p>
        @if($ready)
            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <form method="POST" action="{{ route('content-management.front-office.pos.sales.store') }}" class="bg-white border rounded p-3 h-100">
                        @csrf
                        <h5 class="mb-3">New bill</h5>
                        <div class="mb-2">
                            <label class="form-label" for="bill-names">Guest</label>
                            <input class="form-control" id="bill-names" name="names" value="{{ old('names') }}" required maxlength="255">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="bill-phone">Phone</label>
                            <input class="form-control" id="bill-phone" name="phone" value="{{ old('phone') }}" maxlength="30">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="bill-email">Email</label>
                            <input class="form-control" id="bill-email" type="email" name="email" value="{{ old('email') }}" maxlength="255">
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="bill-guests">Guests</label>
                                <input class="form-control" id="bill-guests" type="number" name="guests" min="1" max="40" value="{{ old('guests', 1) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="bill-tier">Prices</label>
                                <select class="form-select" id="bill-tier" name="price_tier" required>
                                    <option value="international" @selected(old('price_tier', 'international') === 'international')>USD</option>
                                    <option value="local" @selected(old('price_tier') === 'local')>RWF</option>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-dark mt-3" type="submit">Open bill</button>
                    </form>
                </div>
                <div class="col-lg-8">
                    <div class="bg-white border rounded p-3 h-100">
                        <h5 class="mb-3">Recent bills</h5>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Bill</th><th>When</th><th class="text-end">Total</th><th>Payment</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($recent as $bill)
                                        @php $open = $bill->accessGranted(); @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('content-management.restaurant-reservations.show', $bill) }}">{{ $bill->names }}</a>
                                                <div class="text-muted small">{{ $bill->reference }}</div>
                                            </td>
                                            <td>{{ optional($bill->reserved_for)->format('d M Y · H:i') }}</td>
                                            <td class="text-end">{{ $bill->money((float) $bill->total_amount) }}</td>
                                            <td>
                                                <span style="display:inline-block;padding:0.15rem 0.55rem;border-radius:999px;background:{{ $open ? '#1f7a3a' : '#e2b100' }};color:{{ $open ? '#fff' : '#1a1a1a' }};">{{ $open ? 'Paid' : 'Not paid' }}</span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <a href="{{ route('content-management.restaurant-reservations.show', $bill) }}">View bill</a>
                                                <a class="ms-2" href="{{ route('content-management.restaurant-reservations.show', $bill) }}?print=1" target="_blank" rel="noopener">Print</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted">No restaurant bills yet. Open one with the guest’s name.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('content-management.restaurant-reservations.show', $visit) }}">View bill</a>
                                    <a class="ms-2" href="{{ route('content-management.restaurant-reservations.show', $visit) }}?print=1" target="_blank" rel="noopener">Print</a>
                                </td>
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
