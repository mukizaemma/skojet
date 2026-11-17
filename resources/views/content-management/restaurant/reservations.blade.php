@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-reserve-search">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h4 class="mb-0">Reservations</h4>
            <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.pos') }}">POS</a>
        </div>

        <details class="bg-white border rounded p-3 mb-3" @if($errors->any()) open @endif>
            <summary class="fw-semibold">New reservation</summary>
            <form method="POST" action="{{ route('content-management.restaurant-reservations.store') }}" class="mt-3">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Guest</label>
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
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input class="form-control" type="date" name="reserved_date" value="{{ old('reserved_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time</label>
                        <input class="form-control" type="time" name="reserved_time" value="{{ old('reserved_time', '19:00') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Guests</label>
                        <input class="form-control" type="number" name="guests" min="1" max="40" value="{{ old('guests', 2) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Prices</label>
                        <select class="form-select" name="price_tier" required>
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
                    <div class="col-12">
                        <label class="form-label">Menu</label>
                        <div class="desk-reserve-menu">
                            @forelse($menuGroups as $category => $items)
                                <div class="desk-reserve-menu__group">
                                    <div class="desk-reserve-menu__label">{{ \App\Models\RestaurantMenuItem::CATEGORIES[$category] ?? ucfirst($category) }}</div>
                                    @foreach($items as $item)
                                        <label class="desk-reserve-menu__item">
                                            <span>{{ $item->name }}</span>
                                            <input type="number" name="items[{{ $item->id }}]" min="0" max="99" value="{{ old('items.'.$item->id, 0) }}">
                                        </label>
                                    @endforeach
                                </div>
                            @empty
                                <p class="text-muted mb-0">No menu items yet. The reservation can still be saved, and items can be added on the bill.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Amount paid now</label>
                        <input class="form-control" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', '0') }}">
                        <p class="text-muted small mb-0 mt-1">Leave 0 when nothing has been paid.</p>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <button class="btn btn-dark" type="submit">Save reservation</button>
                    </div>
                </div>
            </form>
        </details>

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
