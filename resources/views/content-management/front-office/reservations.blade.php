@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="pos-head">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.home'), 'label' => 'Front Office'])
                <h4>Room reservations</h4>
            </div>
            <a href="{{ route('content-management.front-office.create') }}" class="btn btn-dark">New reservation</a>
        </div>
        <form id="stay-search" method="GET" class="bg-white border rounded p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="stay-q">Search</label>
                    <input class="form-control" id="stay-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, phone, email, or booking number" autocomplete="off">
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
                    <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.reservations.export.pdf', request()->only(['q', 'start_date', 'end_date'])) }}">PDF</a>
                    <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.reservations.export.xls', request()->only(['q', 'start_date', 'end_date'])) }}">Excel</a>
                    @include('content-management.front-office.partials.clear-filters', ['href' => route('content-management.front-office.reservations')])
                </div>
            </div>
        </form>
        <div class="bg-white border rounded">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Suite</th>
                            <th>Stay</th>
                            <th>Submitted</th>
                            <th>Created by</th>
                            <th>Rate</th>
                            <th>Came from</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Balance</th>
                            <th class="text-nowrap">Check-in / Check-out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $booking)
                            <tr>
                                <td><a href="{{ route('content-management.front-office.show', $booking) }}">{{ $booking->names }}</a></td>
                                <td>{{ $booking->stayRoom()?->title ?? 'Unallocated' }}</td>
                                <td>{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }}</td>
                                <td>{{ optional($booking->created_at)->format('d M Y H:i') }}</td>
                                <td>{{ $booking->submittedByName() }}</td>
                                <td>{{ $booking->staySummary() }}</td>
                                <td>{{ $booking->channelLabel() }} · {{ $booking->sourceLabel() }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $booking->deskState()) }}</td>
                                <td class="text-end">{{ hotel_price($booking->total_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                                <td class="text-end">{{ hotel_price($booking->balance_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                                <td>@include('content-management.front-office.partials.stay-actions')</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-muted p-3">No reservations match this search.</td></tr>
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
        const form = document.getElementById('stay-search');
        const box = document.getElementById('stay-q');
        if (!form || !box) {
            return;
        }
        let timer = null;
        box.addEventListener('input', function () {
            window.sessionStorage.setItem('stay-search-focus', '1');
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
        if (window.sessionStorage.getItem('stay-search-focus') === '1') {
            window.sessionStorage.removeItem('stay-search-focus');
            box.focus();
            const end = box.value.length;
            box.setSelectionRange(end, end);
        }
    })();
</script>
@endsection
