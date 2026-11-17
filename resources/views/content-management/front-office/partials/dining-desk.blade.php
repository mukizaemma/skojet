<div class="col-12">
<div class="bg-white border rounded p-3 h-100">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">Dining</h5>
            <p class="text-muted small mb-0">{{ $dining['todayCount'] }} today · {{ $dining['unpaid'] }} waiting on payment</p>
        </div>
        <a class="btn btn-dark btn-sm" href="{{ route('content-management.front-office.pos') }}">Open POS</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>When</th>
                    <th>Access</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dining['visits'] as $reservation)
                    <tr>
                        <td>
                            <a href="{{ route('content-management.restaurant-reservations.show', $reservation) }}">{{ $reservation->names }}</a>
                            <div class="text-muted small">{{ $reservation->reference }}</div>
                        </td>
                        <td>{{ optional($reservation->reserved_for)->format('D, M j · H:i') }}</td>
                        <td>
                            @php $open = $reservation->accessGranted(); @endphp
                            <span style="display:inline-block;padding:0.15rem 0.55rem;border-radius:999px;background:{{ $open ? '#1f7a3a' : '#e2b100' }};color:{{ $open ? '#fff' : '#1a1a1a' }};">{{ $open ? 'Paid' : 'Not paid' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">No dining reservations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @unless($dining['showingToday'])
        <p class="text-muted small mb-0 mt-2">Nothing is booked for today. These are the next reservations.</p>
    @endunless
</div>
</div>
