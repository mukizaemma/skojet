@php
    $channel = old('reservation_channel', $booking->reservation_channel ?: ($booking->booking_type === 'online' ? 'website' : 'walkin'));
@endphp
<div class="desk-cart__actions">
    <button class="btn btn-dark" type="submit" form="desk-stay" name="after" value="save">Save reservation</button>
    @if($booking->exists && $booking->checked_in_at && ! $booking->checked_out_at)
        <a class="btn btn-dark" href="{{ route('content-management.front-office.check-out.form', $booking) }}">Check out</a>
    @elseif($booking->exists && ! $booking->checked_in_at)
        <a class="btn btn-success" href="{{ route('content-management.front-office.check-in.form', $booking) }}">Check in guest</a>
    @elseif(! $booking->checked_in_at)
        <button class="btn btn-success" type="submit" form="desk-stay" name="after" value="checkin" data-desk-walkin @if(! $booking->exists && $channel !== 'walkin') hidden @endif>Check in guest</button>
    @endif
    <button class="btn btn-outline-dark" type="submit" form="desk-stay" name="after" value="payment">Confirm payment</button>
    <button class="btn btn-outline-dark" type="submit" form="desk-stay" name="after" value="invoice">Print invoice</button>
</div>
