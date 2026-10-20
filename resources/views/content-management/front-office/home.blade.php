@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="mb-1">Front Office</h4>
                <p class="text-muted mb-0">Recent stays, arrivals, and balances.</p>
            </div>
            <a href="{{ route('content-management.front-office.create') }}" class="btn btn-dark">New reservation</a>
        </div>
        <div class="row g-3 mb-4">
            @foreach ([
                ['Arriving today', $stats['arriving']],
                ['In house', $stats['in_house']],
                ['Departing today', $stats['departing']],
                ['Outstanding', hotel_price($stats['balance'], $setting ?? null)],
            ] as [$label, $value])
                <div class="col-sm-6 col-xl-3">
                    <div class="bg-white border rounded p-3 h-100">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="bg-white border rounded p-3">
            <h5 class="mb-3">Recent reservations</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Suite</th>
                            <th>Stay</th>
                            <th>Status</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $booking)
                            <tr>
                                <td><a href="{{ route('content-management.front-office.show', $booking) }}">{{ $booking->names }}</a></td>
                                <td>{{ $booking->stayRoom()?->title ?? 'Unallocated' }}</td>
                                <td>{{ optional($booking->checkin_date)->format('d M') }} – {{ optional($booking->checkout_date)->format('d M Y') }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $booking->deskState()) }}</td>
                                <td class="text-end">{{ hotel_price($booking->balance_amount, $setting ?? null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No reservations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
