@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        <h4 class="mb-3">Reports</h4>
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
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Room total</div><div class="fs-4">{{ hotel_price($total, $setting ?? null) }}</div></div></div>
            <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Received</div><div class="fs-4">{{ hotel_price($paid, $setting ?? null) }}</div></div></div>
            <div class="col-md-4"><div class="bg-white border rounded p-3"><div class="text-muted small">Outstanding</div><div class="fs-4">{{ hotel_price($balance, $setting ?? null) }}</div></div></div>
        </div>
        <div class="bg-white border rounded">
            <table class="table mb-0">
                <thead><tr><th>Guest</th><th>Check in</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td><a href="{{ route('content-management.front-office.show', $booking) }}">{{ $booking->names }}</a></td>
                            <td>{{ optional($booking->checkin_date)->format('d M Y') }}</td>
                            <td class="text-end">{{ hotel_price($booking->total_amount, $setting ?? null) }}</td>
                            <td class="text-end">{{ hotel_price($booking->paid_amount, $setting ?? null) }}</td>
                            <td class="text-end">{{ hotel_price($booking->balance_amount, $setting ?? null) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted p-3">No stays in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
