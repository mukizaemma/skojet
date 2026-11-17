@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos-cart">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <a class="desk-pos__back" href="{{ route('content-management.front-office.pos') }}">POS</a>
        <h4 class="mb-3">Sales</h4>
        @if($ready)
            @if($menuItems === [])
                <div class="bg-white border rounded p-3 mb-4 text-muted">The menu is empty. Add items under POS → Menu management, then come back here to sell them.</div>
            @else
                <div class="pos-sale mb-4" id="pos-sale">
                    <div class="pos-menu">
                        <div class="pos-bar">
                            <input class="pos-search" id="pos-search" type="search" placeholder="Search the menu" autocomplete="off">
                            <div class="pos-tabs" id="pos-tabs" role="tablist"></div>
                        </div>
                        <div class="pos-grid" id="pos-grid"></div>
                        <p class="text-muted mb-0" id="pos-none" hidden>Nothing on the menu matches that search.</p>
                    </div>
                    <form class="pos-cart" id="pos-cart" method="POST" action="{{ route('content-management.front-office.pos.sales.store') }}">
                        @csrf
                        <button class="pos-cart__head" id="pos-cart-toggle" type="button">
                            <span>Cart <span id="pos-count">0</span></span>
                            <strong id="pos-head-total">$0</strong>
                        </button>
                        <div class="pos-cart__body" id="pos-cart-body">
                            <div id="pos-lines"></div>
                            <p class="text-muted mb-2" id="pos-empty">Tap an item to add it.</p>
                            <div class="pos-total"><span>Total</span><strong id="pos-total">$0</strong></div>
                            <div id="pos-fields"></div>
                            <label class="form-label mt-2" for="bill-names">Guest</label>
                            <input class="form-control" id="bill-names" name="names" value="{{ old('names') }}" maxlength="255" placeholder="Walk-in">
                            <div class="row g-2 mt-1">
                                <div class="col-7">
                                    <label class="form-label" for="bill-phone">Phone</label>
                                    <input class="form-control" id="bill-phone" name="phone" value="{{ old('phone') }}" maxlength="30">
                                </div>
                                <div class="col-5">
                                    <label class="form-label" for="bill-guests">Guests</label>
                                    <input class="form-control" id="bill-guests" type="number" name="guests" min="1" max="40" value="{{ old('guests', 1) }}">
                                </div>
                            </div>
                            <div class="pos-tiers" role="radiogroup" aria-label="Prices">
                                <button type="button" data-tier="international">USD</button>
                                <button type="button" data-tier="local">RWF</button>
                            </div>
                            <input type="hidden" name="price_tier" id="pos-tier" value="{{ old('price_tier', 'international') }}">
                            <label class="form-label" for="bill-paid">Paid now</label>
                            <div class="pos-paid">
                                <input class="form-control" id="bill-paid" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', '0') }}">
                                <button class="btn btn-outline-dark" id="pos-paid-full" type="button">Full</button>
                            </div>
                            <button class="btn btn-dark w-100 mt-3" id="pos-place" type="submit" disabled>Place order</button>
                        </div>
                    </form>
                </div>
                <script type="application/json" id="pos-menu-data">@json($menuItems)</script>
                <script type="application/json" id="pos-cart-data">@json($oldCart)</script>
                <script>
                    (function () {
                        const items = JSON.parse(document.getElementById('pos-menu-data').textContent || '[]');
                        const saved = JSON.parse(document.getElementById('pos-cart-data').textContent || '{}');
                        const cart = {};
                        Object.keys(saved).forEach(function (id) {
                            const qty = parseInt(saved[id], 10);
                            if (qty > 0) cart[id] = qty;
                        });
                        const byId = {};
                        items.forEach(function (item) { byId[String(item.id)] = item; });
                        const tabs = document.getElementById('pos-tabs');
                        const grid = document.getElementById('pos-grid');
                        const lines = document.getElementById('pos-lines');
                        const fields = document.getElementById('pos-fields');
                        const search = document.getElementById('pos-search');
                        const none = document.getElementById('pos-none');
                        const empty = document.getElementById('pos-empty');
                        const tierInput = document.getElementById('pos-tier');
                        const paidInput = document.getElementById('bill-paid');
                        const place = document.getElementById('pos-place');
                        const form = document.getElementById('pos-cart');
                        let category = 'all';

                        function money(amount) {
                            const value = Number(amount) || 0;
                            if (tierInput.value === 'local') {
                                return 'RWF ' + Math.round(value).toLocaleString('en-US');
                            }
                            const decimals = Math.abs(value - Math.round(value)) < 0.001 ? 0 : 2;
                            return '$' + value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                        }

                        function priceOf(item) {
                            return tierInput.value === 'local' ? Number(item.rwf) : Number(item.usd);
                        }

                        function total() {
                            return Object.keys(cart).reduce(function (sum, id) {
                                const item = byId[id];
                                return item ? sum + priceOf(item) * cart[id] : sum;
                            }, 0);
                        }

                        function renderTabs() {
                            const categories = [{ key: 'all', label: 'All' }];
                            const seen = {};
                            items.forEach(function (item) {
                                if (!seen[item.category]) {
                                    seen[item.category] = true;
                                    categories.push({ key: item.category, label: item.category_label });
                                }
                            });
                            tabs.textContent = '';
                            categories.forEach(function (entry) {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.textContent = entry.label;
                                button.className = entry.key === category ? 'is-current' : '';
                                button.addEventListener('click', function () {
                                    category = entry.key;
                                    renderTabs();
                                    renderGrid();
                                });
                                tabs.appendChild(button);
                            });
                        }

                        function renderGrid() {
                            const term = search.value.trim().toLowerCase();
                            grid.textContent = '';
                            let shown = 0;
                            items.forEach(function (item) {
                                const haystack = (item.name + ' ' + (item.description || '')).toLowerCase();
                                if (category !== 'all' && item.category !== category) return;
                                if (term && haystack.indexOf(term) === -1) return;
                                shown += 1;
                                const id = String(item.id);
                                const qty = cart[id] || 0;
                                const tile = document.createElement('button');
                                tile.type = 'button';
                                tile.className = 'pos-tile' + (qty > 0 ? ' is-in' : '');
                                const name = document.createElement('strong');
                                name.textContent = item.name;
                                const price = document.createElement('span');
                                price.className = 'pos-tile__price';
                                price.textContent = money(priceOf(item));
                                tile.appendChild(name);
                                if (item.description) {
                                    const note = document.createElement('span');
                                    note.className = 'pos-tile__note';
                                    note.textContent = item.description;
                                    tile.appendChild(note);
                                }
                                tile.appendChild(price);
                                if (qty > 0) {
                                    const badge = document.createElement('span');
                                    badge.className = 'pos-tile__qty';
                                    badge.textContent = String(qty);
                                    tile.appendChild(badge);
                                    const minus = document.createElement('span');
                                    minus.className = 'pos-tile__minus';
                                    minus.textContent = '−';
                                    minus.addEventListener('click', function (event) {
                                        event.stopPropagation();
                                        change(id, -1);
                                    });
                                    tile.appendChild(minus);
                                }
                                tile.addEventListener('click', function () { change(id, 1); });
                                grid.appendChild(tile);
                            });
                            none.hidden = shown > 0;
                        }

                        function renderCart() {
                            lines.textContent = '';
                            fields.textContent = '';
                            const ids = Object.keys(cart);
                            empty.hidden = ids.length > 0;
                            ids.forEach(function (id) {
                                const item = byId[id];
                                if (!item) {
                                    delete cart[id];
                                    return;
                                }
                                const row = document.createElement('div');
                                row.className = 'pos-line';
                                const name = document.createElement('div');
                                name.className = 'pos-line__name';
                                name.textContent = item.name;
                                const qty = document.createElement('div');
                                qty.className = 'pos-qty';
                                const minus = document.createElement('button');
                                minus.type = 'button';
                                minus.textContent = '−';
                                minus.addEventListener('click', function () { change(id, -1); });
                                const count = document.createElement('span');
                                count.textContent = String(cart[id]);
                                const plus = document.createElement('button');
                                plus.type = 'button';
                                plus.textContent = '+';
                                plus.addEventListener('click', function () { change(id, 1); });
                                qty.append(minus, count, plus);
                                const amount = document.createElement('div');
                                amount.className = 'pos-line__amount';
                                amount.textContent = money(priceOf(item) * cart[id]);
                                row.append(name, qty, amount);
                                lines.appendChild(row);
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'items[' + id + ']';
                                input.value = String(cart[id]);
                                fields.appendChild(input);
                            });
                            const sum = total();
                            const label = money(sum);
                            document.getElementById('pos-total').textContent = label;
                            document.getElementById('pos-head-total').textContent = label;
                            document.getElementById('pos-count').textContent = String(ids.reduce(function (count, id) { return count + cart[id]; }, 0));
                            place.disabled = ids.length === 0;
                        }

                        function change(id, delta) {
                            const next = (cart[id] || 0) + delta;
                            if (next <= 0) delete cart[id];
                            else cart[id] = Math.min(99, next);
                            renderGrid();
                            renderCart();
                        }

                        document.querySelectorAll('.pos-tiers button').forEach(function (button) {
                            button.addEventListener('click', function () {
                                tierInput.value = button.getAttribute('data-tier');
                                paintTiers();
                                renderGrid();
                                renderCart();
                            });
                        });

                        function paintTiers() {
                            document.querySelectorAll('.pos-tiers button').forEach(function (button) {
                                button.classList.toggle('is-current', button.getAttribute('data-tier') === tierInput.value);
                            });
                        }

                        document.getElementById('pos-paid-full').addEventListener('click', function () {
                            const sum = total();
                            paidInput.value = tierInput.value === 'local' ? String(Math.round(sum)) : sum.toFixed(2);
                        });
                        document.getElementById('pos-cart-toggle').addEventListener('click', function () {
                            if (window.matchMedia('(max-width: 991.98px)').matches) {
                                form.classList.toggle('is-open');
                            }
                        });
                        search.addEventListener('input', renderGrid);
                        form.addEventListener('submit', function (event) {
                            if (Object.keys(cart).length === 0) event.preventDefault();
                        });
                        renderTabs();
                        paintTiers();
                        renderGrid();
                        renderCart();
                        if (Object.keys(cart).length > 0) form.classList.add('is-open');
                    })();
                </script>
            @endif
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
            <div class="bg-white border rounded mb-4">
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
            @if($roomCharges->isNotEmpty())
                <h6 class="mb-2">Posted to a room bill</h6>
                <div class="bg-white border rounded">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Guest</th><th>Item</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach($roomCharges as $charge)
                                <tr>
                                    <td>
                                        @if($charge->booking)
                                            <a href="{{ route('content-management.front-office.show', $charge->booking) }}">{{ $charge->booking->names }}</a>
                                        @endif
                                    </td>
                                    <td>{{ $charge->name }}</td>
                                    <td class="text-end">{{ hotel_price($charge->line_total, $setting ?? null, $charge->booking?->currencyKey() ?? 'usd') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
