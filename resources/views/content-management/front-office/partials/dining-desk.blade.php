<div class="col-xl-7">
<div class="bg-white border rounded p-3 h-100">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">Dining</h5>
            <p class="text-muted small mb-0">{{ $dining['todayCount'] }} today · {{ $dining['unpaid'] }} waiting on payment</p>
        </div>
        <a class="btn btn-dark btn-sm" href="{{ route('content-management.restaurant-reservations') }}">All dining reservations</a>
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

<div class="col-xl-5">
<div class="bg-white border rounded p-3 h-100" id="dining-access">
    <h5 class="mb-1">Restaurant access</h5>
    <p class="text-muted small">Guests see the international price unless they choose the local price. They can send a reservation when the order reaches the minimum for that price.</p>
    <form method="POST" action="{{ route('content-management.restaurant-menu.settings') }}" class="row g-3">
        @csrf
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_reservation" id="requires_reservation" value="1" @checked(old('requires_reservation', $dining['restaurant']->requiresReservation()))>
                <label class="form-check-label" for="requires_reservation">Require a reservation before dining</label>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="minimum_amount">Minimum, international (USD)</label>
            <input class="form-control" type="number" min="0" step="0.01" name="minimum_amount" id="minimum_amount" value="{{ old('minimum_amount', number_format((float) $dining['restaurant']->minimum_amount, 2, '.', '')) }}" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="minimum_amount_local">Minimum, local (RWF)</label>
            <input class="form-control" type="number" min="0" step="1" name="minimum_amount_local" id="minimum_amount_local" value="{{ old('minimum_amount_local', (int) $dining['restaurant']->minimum_amount_local) }}" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="momo_code">MoMo code</label>
            <input class="form-control" type="text" name="momo_code" id="momo_code" maxlength="40" value="{{ old('momo_code', $dining['restaurant']->momoCode()) }}" placeholder="Merchant code">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="momo_account_name">Name on the MoMo account</label>
            <input class="form-control" type="text" name="momo_account_name" id="momo_account_name" maxlength="120" value="{{ old('momo_account_name', $dining['restaurant']->momoAccountName()) }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="reserve-url">Reservation link</label>
            <div class="input-group">
                <input class="form-control" id="reserve-url" type="text" readonly value="{{ $dining['reserveUrl'] }}">
                <button class="btn btn-outline-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reserve-url').value)">Copy</button>
                <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-menu.qr') }}" target="_blank" rel="noopener">QR code</a>
            </div>
        </div>
        <div class="col-12">
            <button class="btn btn-dark" type="submit">Save access rules</button>
        </div>
    </form>
</div>
</div>
