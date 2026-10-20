@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
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
                            <th>Rate</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $booking)
                            <tr>
                                <td><a href="{{ route('content-management.front-office.show', $booking) }}">{{ $booking->names }}</a></td>
                                <td>{{ $booking->stayRoom()?->title ?? 'Unallocated' }}</td>
                                <td>{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }}</td>
                                <td>{{ \App\Models\Room::BOARD_PLANS[$booking->board] ?? 'Standard' }}{{ $booking->occupancy ? ' · '.ucfirst($booking->occupancy) : '' }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $booking->deskState()) }}</td>
                                <td class="text-end">{{ hotel_price($booking->total_amount, $setting ?? null) }}</td>
                                <td class="text-end">{{ hotel_price($booking->balance_amount, $setting ?? null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No reservations in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $reservations->links() }}</div>
    </div>
</div>
</div>
@endsection
