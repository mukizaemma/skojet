@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="bg-white border rounded p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h4 class="mb-1">Dining reservations</h4>
                    <p class="text-muted mb-0">Open a bill to add items, confirm the payment, then print or download the PDF.</p>
                </div>
                <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.pos') }}">POS</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Guest</th>
                            <th>When</th>
                            <th>Total</th>
                            <th>Access</th>
                            <th>Sent by</th>
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
                                <td>{{ $reservation->submit_via === 'whatsapp' ? 'WhatsApp' : 'Email' }}</td>
                                <td><a href="{{ route('content-management.restaurant-reservations.show', $reservation) }}">Open bill</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No restaurant reservations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $reservations->links() }}</div>
        </div>
    </div>
</div>
@endsection
