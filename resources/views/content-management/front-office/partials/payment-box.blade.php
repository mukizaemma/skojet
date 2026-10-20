@php
    $sessionForm = $sessionForm ?? null;
    $mode = $mode ?? 'checkout';
    $roomTotal = (float) $booking->total_amount;
    $extrasTotal = (float) ($booking->charges_amount ?? 0);
    $grandTotal = $roomTotal + $extrasTotal;
    $paidTotal = (float) $booking->paid_amount;
    $waivedTotal = (float) ($booking->waived_amount ?? 0);
    $balanceTotal = (float) $booking->balance_amount;
    $amountDefault = $balanceTotal > 0 ? number_format($balanceTotal, 2, '.', '') : '';
@endphp
<div class="bg-white border rounded p-3" id="payment">
    <h6 class="mb-2">Record payment</h6>
    <div class="desk-cart__row"><span>Check-in</span><strong>{{ optional($booking->checkin_date)->format('d M Y') ?: '—' }}</strong></div>
    <div class="desk-cart__row"><span>Check-out</span><strong>{{ optional($booking->checkout_date)->format('d M Y') ?: '—' }}</strong></div>
    <div class="desk-cart__row"><span>Room</span><strong>{{ hotel_price($roomTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    @if($extrasTotal >= 0.5)
        <div class="desk-cart__row"><span>Extra charges</span><strong>{{ hotel_price($extrasTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    @endif
    <div class="desk-cart__row"><span>Total</span><strong>{{ hotel_price($grandTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    <div class="desk-cart__row"><span>Paid</span><strong>{{ hotel_price($paidTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    @if($waivedTotal >= 0.5)
        <div class="desk-cart__row"><span>Complimentary</span><strong>{{ hotel_price($waivedTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    @endif
    <div class="desk-cart__row desk-cart__total"><span>Balance</span><strong>{{ hotel_price($balanceTotal, $setting ?? null, $booking->currencyKey()) }}</strong></div>
    <form method="POST" action="{{ route('content-management.front-office.pay', $booking) }}" class="mt-3">
        @csrf
        <div class="mb-2">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $amountDefault) }}" class="form-control" @if($balanceTotal > 0) required @endif>
        </div>
        <div class="mb-2">
            <label class="form-label">Method</label>
            <select name="method" class="form-select">
                @foreach(\App\Models\BookingPayment::METHODS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Note</label>
            <input type="text" name="note" class="form-control" maxlength="255">
        </div>
        <button class="btn btn-dark w-100" type="submit">Confirm payment</button>
    </form>
    @if($balanceTotal > 0)
        <div class="d-grid gap-2 mt-2">
            @if(! empty($sessionForm))
                <button class="btn btn-outline-dark" type="submit" form="{{ $sessionForm }}" name="intent" value="confirm">{{ $mode === 'checkin' ? 'Check in with unpaid balance' : 'Check out with unpaid balance' }}</button>
            @endif
            <form method="POST" action="{{ route('content-management.front-office.complimentary', $booking) }}">
                @csrf
                <button class="btn btn-outline-dark w-100" type="submit">Complimentary bill</button>
            </form>
        </div>
        <p class="text-muted small mt-2 mb-0">Confirm payment takes the amount above. Checking out with a balance still uses the departure date and the confirmation on the left. Complimentary clears the balance without recording a cash payment.</p>
    @endif
</div>
