<p style="margin: 0 0 8px;"><strong>{{ $reservation->reference }}</strong></p>
<p style="margin: 0 0 4px;">{{ $reservation->names }} · {{ $reservation->phone }} · {{ $reservation->email }}</p>
<p style="margin: 0 0 16px;">{{ optional($reservation->reserved_for)->format('D, M j, Y · H:i') }} · {{ $reservation->guests }} {{ $reservation->guests === 1 ? 'guest' : 'guests' }}</p>
<table style="width: 100%; border-collapse: collapse; margin: 0 0 16px;">
    @foreach($reservation->items as $item)
        <tr>
            <td style="padding: 6px 0; border-bottom: 1px solid #eee;">
                {{ $item->quantity }} × {{ $item->name }}
                @if(filled($item->description))
                    <br><span style="color: #666; font-size: 13px;">{{ $item->description }}</span>
                @endif
            </td>
            <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right; white-space: nowrap;">{{ $reservation->money((float) $item->line_total) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="padding: 10px 0;"><strong>Total</strong></td>
        <td style="padding: 10px 0; text-align: right;"><strong>{{ $reservation->money((float) $reservation->total_amount) }}</strong></td>
    </tr>
</table>
@if(filled($reservation->notes))
    <p style="margin: 0 0 8px;"><strong>Note</strong></p>
    <p style="margin: 0 0 16px; white-space: pre-wrap;">{{ $reservation->notes }}</p>
@endif
