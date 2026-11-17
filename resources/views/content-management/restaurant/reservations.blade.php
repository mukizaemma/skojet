@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-reserve-modal">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h4 class="mb-0">Reservations</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-dark btn-sm" type="button" id="open-reservation">New reservation</button>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.pos') }}">POS</a>
            </div>
        </div>

        <form id="reservation-search" method="GET" class="bg-white border rounded p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="reservation-q">Search</label>
                    <input class="form-control" id="reservation-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, phone, email, or reference" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">From</label>
                    <input class="form-control" type="date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label">To</label>
                    <input class="form-control" type="date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>
                <div class="col-lg-4 d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-reservations.export.pdf', request()->only(['q', 'start_date', 'end_date'])) }}">PDF</a>
                    <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-reservations.export.xls', request()->only(['q', 'start_date', 'end_date'])) }}">Excel</a>
                    @if($filters['q'] !== '' || $filters['start_date'] !== '' || $filters['end_date'] !== '')
                        <a class="btn btn-link" href="{{ route('content-management.restaurant-reservations') }}">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="bg-white border rounded">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Guest</th>
                            <th>When</th>
                            <th>Total</th>
                            <th>Access</th>
                            <th>Booked by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $reservation)
                            <tr>
                                <td>{{ $reservation->reference }}</td>
                                <td>
                                    {{ $reservation->names }}<br>
                                    <span class="text-muted small">{{ $reservation->phone }}</span>
                                </td>
                                <td>{{ optional($reservation->reserved_for)->format('D, M j, Y · H:i') }}</td>
                                <td>{{ $reservation->money((float) $reservation->total_amount) }}</td>
                                <td>
                                    @php $open = $reservation->accessGranted(); @endphp
                                    <span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;background:{{ $open ? '#1f7a3a' : '#e2b100' }};color:{{ $open ? '#fff' : '#1a1a1a' }};">
                                        {{ $open ? 'Paid' : 'Not paid' }}
                                    </span>
                                    <div class="small text-muted">{{ $reservation->money((float) $reservation->paid_amount) }}</div>
                                </td>
                                <td>{{ ['whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'desk' => 'At the desk'][$reservation->submit_via] ?? 'Email' }}</td>
                                <td><a href="{{ route('content-management.restaurant-reservations.show', $reservation) }}">Open bill</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No reservations match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $reservations->links() }}</div>
    </div>
</div>

<div class="modal fade" id="reservation-modal" tabindex="-1" aria-labelledby="reservation-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('content-management.restaurant-reservations.store') }}" id="reserve-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="reservation-modal-title">New reservation</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <section class="reserve-form__block">
                        <h6>Guest</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Name</label>
                                <input class="form-control" name="names" value="{{ old('names') }}" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input class="form-control" name="phone" value="{{ old('phone') }}" maxlength="30">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="email" value="{{ old('email') }}" maxlength="255">
                            </div>
                        </div>
                    </section>
                    <section class="reserve-form__block">
                        <h6>Visit</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label">Date</label>
                                <input class="form-control" type="date" name="reserved_date" value="{{ old('reserved_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Time</label>
                                <input class="form-control" type="time" name="reserved_time" value="{{ old('reserved_time', '19:00') }}" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">Guests</label>
                                <input class="form-control" type="number" name="guests" min="1" max="40" value="{{ old('guests', 2) }}">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label" for="reserve-tier">Prices</label>
                                <select class="form-select" id="reserve-tier" name="price_tier" required>
                                    <option value="international" @selected(old('price_tier', 'international') === 'international')>USD</option>
                                    <option value="local" @selected(old('price_tier') === 'local')>RWF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">How it came in</label>
                                <select class="form-select" name="submit_via" required>
                                    <option value="phone" @selected(old('submit_via', 'phone') === 'phone')>Phone call</option>
                                    <option value="desk" @selected(old('submit_via') === 'desk')>Could not book online</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Amount paid now</label>
                                <input class="form-control" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', '0') }}">
                            </div>
                        </div>
                    </section>
                    <section>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="mb-0">Order</h6>
                            <input class="form-control reserve-form__search" id="reserve-menu-search" type="search" placeholder="Search the menu" autocomplete="off">
                        </div>
                        <div class="menu-tabs mb-3" id="reserve-tabs" role="tablist">
                            <button class="menu-tabs__btn is-active" type="button" data-reserve-tab="all">All</button>
                        </div>
                        <div class="reserve-grid" id="reserve-grid">
                            <p class="text-muted mb-0" id="reserve-menu-status">The menu loads when you open this form.</p>
                        </div>
                        <p class="text-muted mt-3 mb-0" id="reserve-menu-none" hidden>No items match this search.</p>
                        <p class="reserve-order" id="reserve-order" hidden></p>
                    </section>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark" type="submit">Save reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const modalEl = document.getElementById('reservation-modal');
        const grid = document.getElementById('reserve-grid');
        const tabs = document.getElementById('reserve-tabs');
        const tier = document.getElementById('reserve-tier');
        const search = document.getElementById('reserve-menu-search');
        const order = document.getElementById('reserve-order');
        const none = document.getElementById('reserve-menu-none');
        const status = document.getElementById('reserve-menu-status');
        const savedQty = @json(old('items', []));
        let category = 'all';
        let loaded = false;
        let loading = false;

        function showModal() {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }
            if (window.jQuery) {
                window.jQuery(modalEl).modal('show');
            }
        }

        function money(amount) {
            const number = Number(amount) || 0;
            if (tier.value === 'local') {
                return 'RWF ' + Math.round(number).toLocaleString();
            }
            const decimals = Math.abs(number - Math.round(number)) < 0.001 ? 0 : 2;
            return '$' + number.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        function paint() {
            const query = search.value.trim().toLowerCase();
            let shown = 0;
            const picked = [];
            grid.querySelectorAll('[data-reserve-card]').forEach(function (card) {
                const qty = Math.max(0, Math.min(99, Number(card.querySelector('[data-qty]').value) || 0));
                card.querySelector('[data-qty]').value = String(qty);
                const badge = card.querySelector('.reserve-card__qty');
                const minus = card.querySelector('.reserve-card__minus');
                badge.textContent = String(qty);
                badge.hidden = qty < 1;
                minus.hidden = qty < 1;
                card.classList.toggle('is-selected', qty > 0);
                const price = tier.value === 'local' ? card.getAttribute('data-rwf') : card.getAttribute('data-usd');
                card.querySelector('.reserve-card__price').textContent = money(price);
                const text = card.getAttribute('data-text') || '';
                const match = (category === 'all' || card.getAttribute('data-category') === category) && (query === '' || text.indexOf(query) !== -1);
                card.hidden = !match;
                if (match) {
                    shown += 1;
                }
                if (qty > 0) {
                    picked.push(qty + ' × ' + card.getAttribute('data-name'));
                }
            });
            none.hidden = !loaded || shown !== 0 || !grid.querySelector('[data-reserve-card]');
            order.hidden = picked.length === 0;
            order.textContent = picked.join(' · ');
        }

        function cardFor(item) {
            const qty = Math.max(0, Number(savedQty[item.id] || savedQty[String(item.id)] || 0));
            const card = document.createElement('div');
            card.className = 'reserve-card' + (qty > 0 ? ' is-selected' : '');
            card.setAttribute('role', 'button');
            card.tabIndex = 0;
            card.setAttribute('data-reserve-card', '');
            card.setAttribute('data-category', item.category);
            card.setAttribute('data-name', item.name);
            card.setAttribute('data-usd', item.usd);
            card.setAttribute('data-rwf', item.rwf);
            card.setAttribute('data-text', (item.name + ' ' + (item.description || '')).toLowerCase());
            const name = document.createElement('strong');
            name.textContent = item.name;
            card.appendChild(name);
            if (item.description) {
                const note = document.createElement('small');
                note.textContent = item.description;
                card.appendChild(note);
            }
            const price = document.createElement('span');
            price.className = 'reserve-card__price';
            card.appendChild(price);
            const badge = document.createElement('span');
            badge.className = 'reserve-card__qty';
            badge.hidden = qty < 1;
            badge.textContent = String(qty);
            card.appendChild(badge);
            const minus = document.createElement('button');
            minus.className = 'reserve-card__minus';
            minus.type = 'button';
            minus.textContent = '−';
            minus.hidden = qty < 1;
            minus.setAttribute('aria-label', 'Remove one ' + item.name);
            card.appendChild(minus);
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[' + item.id + ']';
            input.value = String(qty);
            input.setAttribute('data-qty', '');
            card.appendChild(input);
            return card;
        }

        function loadMenu() {
            if (loaded || loading) {
                return;
            }
            loading = true;
            status.hidden = false;
            status.textContent = 'Loading the menu…';
            fetch(@json(route('content-management.restaurant-reservations.menu')), {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('menu');
                }
                return response.json();
            }).then(function (data) {
                const present = {};
                (data.items || []).forEach(function (item) { present[item.category] = true; });
                Object.keys(data.categories || {}).forEach(function (key) {
                    if (!present[key]) {
                        return;
                    }
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'menu-tabs__btn';
                    button.setAttribute('data-reserve-tab', key);
                    button.textContent = data.categories[key];
                    tabs.appendChild(button);
                });
                status.remove();
                if (!(data.items || []).length) {
                    grid.textContent = '';
                    const empty = document.createElement('p');
                    empty.className = 'text-muted mb-0';
                    empty.textContent = 'No menu items yet. The reservation can still be saved.';
                    grid.appendChild(empty);
                } else {
                    (data.items || []).forEach(function (item) {
                        grid.appendChild(cardFor(item));
                    });
                }
                loaded = true;
                loading = false;
                paint();
            }).catch(function () {
                loading = false;
                status.hidden = false;
                status.textContent = 'The menu could not be loaded. Close this form and try again.';
            });
        }

        function change(card, delta) {
            const input = card.querySelector('[data-qty]');
            input.value = String(Math.max(0, Math.min(99, (Number(input.value) || 0) + delta)));
            paint();
        }

        document.getElementById('open-reservation').addEventListener('click', function () {
            showModal();
            loadMenu();
        });
        grid.addEventListener('click', function (event) {
            const minus = event.target.closest('.reserve-card__minus');
            const card = event.target.closest('[data-reserve-card]');
            if (!card) {
                return;
            }
            event.preventDefault();
            change(card, minus ? -1 : 1);
        });
        grid.addEventListener('keydown', function (event) {
            const card = event.target.closest('[data-reserve-card]');
            if (!card || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }
            event.preventDefault();
            change(card, 1);
        });
        tabs.addEventListener('click', function (event) {
            const tab = event.target.closest('[data-reserve-tab]');
            if (!tab) {
                return;
            }
            category = tab.getAttribute('data-reserve-tab');
            tabs.querySelectorAll('[data-reserve-tab]').forEach(function (button) {
                button.classList.toggle('is-active', button === tab);
            });
            paint();
        });
        search.addEventListener('input', paint);
        tier.addEventListener('change', paint);

        @if($errors->any())
            showModal();
            loadMenu();
        @endif
    })();
</script>
<script>
    (function () {
        const form = document.getElementById('reservation-search');
        const box = document.getElementById('reservation-q');
        if (!form || !box) {
            return;
        }
        let timer = null;
        box.addEventListener('input', function () {
            window.sessionStorage.setItem('reservation-search-focus', '1');
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                form.requestSubmit();
            }, 350);
        });
        form.querySelectorAll('input[type="date"]').forEach(function (field) {
            field.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
        if (window.sessionStorage.getItem('reservation-search-focus') === '1') {
            window.sessionStorage.removeItem('reservation-search-focus');
            box.focus();
            const end = box.value.length;
            box.setSelectionRange(end, end);
        }
    })();
</script>
@endsection
