<h2 style="margin:0 0 8px;">Restaurant reservations</h2>
<p style="margin:0 0 12px;color:#444;">
    @if($filters['q'] !== '') Search: {{ $filters['q'] }}. @endif
    @if($filters['start_date'] !== '' || $filters['end_date'] !== '')
        Dates: {{ $filters['start_date'] !== '' ? $filters['start_date'] : 'any' }} – {{ $filters['end_date'] !== '' ? $filters['end_date'] : 'any' }}.
    @endif
    {{ $reservations->count() }} {{ $reservations->count() === 1 ? 'reservation' : 'reservations' }}.
</p>
<table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead>
        <tr>
            @foreach(['Reference', 'Guest', 'When', 'Total', 'Paid', 'Payment', 'Booked by'] as $heading)
                <th style="text-align:left;border-bottom:1px solid #ccc;padding:6px 4px;">{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($reservations as $reservation)
            <tr>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $reservation->reference }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $reservation->names }}<br>{{ $reservation->phone }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ optional($reservation->reserved_for)->format('d M Y H:i') }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $reservation->money((float) $reservation->total_amount) }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $reservation->money((float) $reservation->paid_amount) }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ $reservation->accessGranted() ? 'Paid' : 'Not paid' }}</td>
                <td style="padding:5px 4px;border-bottom:1px solid #eee;">{{ ['whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'desk' => 'At the desk'][$reservation->submit_via] ?? 'Email' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="padding:8px 4px;">No reservations match this search.</td></tr>
        @endforelse
    </tbody>
</table>
