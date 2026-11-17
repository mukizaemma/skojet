@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="pos-head">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.pos'), 'label' => 'POS'])
                <h4>Restaurant sales</h4>
            </div>
            @include('content-management.front-office.partials.pos-nav', ['current' => 'sales'])
        </div>
        @if($ready)
            @if($menuItems === [])
                <div class="bg-white border rounded p-3 mb-4 text-muted">The menu is empty. Add items under POS → Menu management, then come back here to sell them.</div>
            @else
                <div class="pos-sale mb-4" id="pos-sale">
                    <div class="pos-menu">
                        <div class="pos-bar">
                            <div class="d-flex gap-2">
                                <input class="pos-search" id="pos-search" type="search" placeholder="Search the menu" autocomplete="off">
                                <button class="desk-clear" id="pos-search-clear" type="button">Clear filters</button>
                            </div>
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
                        document.getElementById('pos-search-clear')?.addEventListener('click', function () {
                            search.value = '';
                            category = 'all';
                            renderTabs();
                            renderGrid();
                        });
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
        @else
            <div class="bg-white border rounded p-3 text-muted">Restaurant sales appear after the dining tables are created. Run the database migration, then open this page again.</div>
        @endif
    </div>
</div>
@endsection
