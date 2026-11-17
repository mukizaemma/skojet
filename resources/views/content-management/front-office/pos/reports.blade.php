@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="pos-head">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.pos'), 'label' => 'POS'])
                <h4>Restaurant reports</h4>
            </div>
            @include('content-management.front-office.partials.pos-nav', ['current' => 'reports'])
        </div>
        @php
            $categoryLabel = $category !== '' ? \App\Models\RestaurantMenuItem::CATEGORIES[$category] : 'All categories';
            $itemLabel = 'All items';
            if (str_starts_with($item, 'id:')) {
                $itemLabel = optional($menuItems->firstWhere('id', (int) substr($item, 3)))->name ?? 'Menu item';
            } elseif (str_starts_with($item, 'name:')) {
                $itemLabel = rawurldecode(substr($item, 5));
            }
        @endphp
        <p class="pos-report-print">{{ $start }} – {{ $end }} · {{ $categoryLabel }} · {{ $itemLabel }}</p>
        <form method="GET" class="pos-report-filters bg-white border rounded p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">From</label>
                    <input type="date" name="start_date" value="{{ $start }}" class="form-control">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">To</label>
                    <input type="date" name="end_date" value="{{ $end }}" class="form-control">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1" for="report-category">Category</label>
                    <select class="form-select" id="report-category" name="category">
                        <option value="">All</option>
                        @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label mb-1" for="report-item">Menu item</label>
                    <select class="form-select" id="report-item" name="item">
                        <option value="">All items</option>
                        @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $label)
                            @php $group = $menuItems->where('category', $key); @endphp
                            @if($group->isNotEmpty())
                                <optgroup label="{{ $label }}" data-category="{{ $key }}">
                                    @foreach($group as $menuItem)
                                        <option value="id:{{ $menuItem->id }}" data-category="{{ $key }}" @selected($item === 'id:'.$menuItem->id)>{{ $menuItem->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                        @if($customItems->isNotEmpty())
                            <optgroup label="Custom" data-category="custom">
                                @foreach($customItems as $custom)
                                    @php $customValue = 'name:'.rawurlencode($custom->name); @endphp
                                    <option value="{{ $customValue }}" data-category="{{ $custom->category }}" @selected($item === $customValue)>{{ $custom->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="col-lg-3 d-flex flex-wrap gap-2">
                    <button class="btn btn-dark" type="submit">Update</button>
                    @include('content-management.front-office.partials.clear-filters', ['href' => route('content-management.front-office.pos.reports')])
                    <button class="btn btn-outline-dark" type="button" onclick="window.print()">Print</button>
                </div>
            </div>
        </form>
        @if(! $ready)
            <div class="bg-white border rounded p-3 text-muted">Restaurant reports appear after the dining tables are created. Run the database migration, then open this page again.</div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="bg-white border rounded p-3 h-100">
                        <div class="text-muted small">Bills</div>
                        <div class="fs-4">{{ $summary['bills'] }}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="bg-white border rounded p-3 h-100">
                        <div class="text-muted small">Quantity</div>
                        <div class="fs-4">{{ $summary['quantity'] }}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="bg-white border rounded p-3 h-100">
                        <div class="text-muted small">International (USD)</div>
                        <div class="fs-4">{{ restaurant_money($summary['international']['total'], 'international') }}</div>
                        <div class="text-muted small">Paid {{ restaurant_money($summary['international']['paid'], 'international') }} · due {{ restaurant_money($summary['international']['due'], 'international') }}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="bg-white border rounded p-3 h-100">
                        <div class="text-muted small">Local (RWF)</div>
                        <div class="fs-4">{{ restaurant_money($summary['local']['total'], 'local') }}</div>
                        <div class="text-muted small">Paid {{ restaurant_money($summary['local']['paid'], 'local') }} · due {{ restaurant_money($summary['local']['due'], 'local') }}</div>
                    </div>
                </div>
            </div>
            <div class="bg-white border rounded">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Bill</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            @php $paid = $line->reservation?->payment_status === 'paid'; @endphp
                            <tr>
                                <td>{{ optional($line->reservation?->reserved_for)->format('d M Y · H:i') }}</td>
                                <td>
                                    @if($line->reservation)
                                        <a href="{{ route('content-management.restaurant-reservations.show', $line->reservation) }}">{{ $line->reservation->names }}</a>
                                        <div class="text-muted small">{{ $line->reservation->reference }}</div>
                                    @endif
                                </td>
                                <td>{{ $line->name }}</td>
                                <td>{{ \App\Models\RestaurantMenuItem::CATEGORIES[$line->category] ?? ucfirst((string) $line->category) }}</td>
                                <td class="text-end">{{ $line->quantity }}</td>
                                <td class="text-end">{{ $line->reservation ? $line->reservation->money((float) $line->line_total) : $line->line_total }}</td>
                                <td>
                                    <span style="display:inline-block;padding:0.15rem 0.55rem;border-radius:999px;background:{{ $paid ? '#1f7a3a' : '#e2b100' }};color:{{ $paid ? '#fff' : '#1a1a1a' }};">{{ $paid ? 'Paid' : 'Not paid' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No sales match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
<script>
    (function () {
        const category = document.getElementById('report-category');
        const item = document.getElementById('report-item');
        if (!category || !item) return;
        const sync = function () {
            const chosen = category.value;
            Array.from(item.options).forEach(function (option) {
                if (!option.value) return;
                option.hidden = chosen !== '' && option.getAttribute('data-category') !== chosen;
            });
            item.querySelectorAll('optgroup').forEach(function (group) {
                const visible = Array.from(group.querySelectorAll('option')).some(function (option) { return !option.hidden; });
                group.hidden = !visible;
            });
            if (item.selectedOptions[0] && item.selectedOptions[0].hidden) item.value = '';
        };
        category.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
