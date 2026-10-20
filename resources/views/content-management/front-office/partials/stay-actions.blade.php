@if($booking->status === 'cancelled')
    <span class="text-muted">—</span>
@elseif($booking->checked_out_at)
    <span class="text-muted">Checked out</span>
@elseif($booking->checked_in_at)
    <a class="btn btn-sm btn-dark" href="{{ route('content-management.front-office.check-out.form', $booking) }}">Check out</a>
@else
    <a class="btn btn-sm btn-success" href="{{ route('content-management.front-office.check-in.form', $booking) }}">Check in</a>
@endif
