@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <h4 class="mb-0">Reservations</h4>
            <a href="{{ route('content-management.front-office.create') }}" class="btn btn-dark">New reservation</a>
        </div>
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-auto">
                <label class="form-label mb-1">From</label>
                <input type="date" name="start_date" value="{{ $start }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1">To</label>
                <input type="date" name="end_date" value="{{ $end }}" class="form-control">
            </div>
            <div class="col-auto">
                <button class="btn btn-dark" type="submit">Filter</button>
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
                            <tr><td colspan="11" class="text-muted p-3">No reservations in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $reservations->links() }}</div>
    </div>
</div>
@endsection
