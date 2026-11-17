<h2 style="margin:0 0 8px;">Room reservations</h2>
<p style="margin:0 0 12px;color:#444;">
    @if($filters['q'] !== '') Search: {{ $filters['q'] }}. @endif
    @if($filters['start_date'] !== '' || $filters['end_date'] !== '')
        Dates: {{ $filters['start_date'] !== '' ? $filters['start_date'] : 'any' }} – {{ $filters['end_date'] !== '' ? $filters['end_date'] : 'any' }}.
    @endif
    {{ $reservations->count() }} {{ $reservations->count() === 1 ? 'reservation' : 'reservations' }}.
</p>
<table style="width:100%;border-collapse:collapse;font-size:11px;">
    <thead>
        <tr>
            @foreach(['Guest', 'Suite', 'Stay', 'Submitted', 'Created by', 'Status', 'Total', 'Balance'] as $heading)
                <th style="text-align:left;border-bottom:1px solid #ccc;padding:6px 4px;">{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($reservations as $booking)
            <tr>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $booking->names }}<br>{{ $booking->phone }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $booking->stayRoom()?->title ?? 'Unallocated' }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ optional($booking->created_at)->format('d M Y H:i') }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $booking->submittedByName() }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ ucwords(str_replace('_', ' ', $booking->deskState())) }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ hotel_price($booking->total_amount, $setting ?? null, $booking->currencyKey()) }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ hotel_price($booking->balance_amount, $setting ?? null, $booking->currencyKey()) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="padding:8px 4px;">No reservations match this search.</td></tr>
        @endforelse
    </tbody>
</table>
