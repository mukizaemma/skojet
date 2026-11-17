@php
    $invoice = $invoice ?? hotel_invoice($setting ?? null);
    $thanks = trim((string) ($invoice['footer'] ?? ''));
    if ($thanks === '') {
        $thanks = 'Thank you. We look forward to welcoming you again.';
    }
    $contacts = collect([$invoice['phone'], $invoice['email']])->filter()->implode(' · ');
    $currency = $booking->currencyKey();
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e4ddd2;border-collapse:collapse;font-family:DejaVu Sans, Georgia, sans-serif;color:#1a1a1a;">
    <tr>
        <td style="padding:28px 28px 18px;text-align:center;border-bottom:1px solid #e4ddd2;">
            <div style="font-size:22px;line-height:1.2;">{{ $invoice['name'] }}</div>
            @if($invoice['address'] !== '')
                <div style="margin-top:8px;font-size:13px;line-height:1.5;">{{ $invoice['address'] }}</div>
            @endif
            @if($contacts !== '')
                <div style="margin-top:4px;font-size:13px;">{{ $contacts }}</div>
            @endif
            @if($invoice['tax_id'] !== '')
                <div style="margin-top:4px;font-size:12px;">Tax ID {{ $invoice['tax_id'] }}</div>
            @endif
            <div style="margin-top:16px;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;">Guest bill</div>
            <div style="margin-top:4px;font-size:18px;">Reservation {{ $booking->id }}</div>
        </td>
    </tr>
    <tr>
        <td style="padding:20px 28px 8px;font-size:14px;line-height:1.5;">
            <strong>{{ $booking->names }}</strong><br>
            {{ collect([$booking->phone, $booking->email])->filter()->implode(' · ') }}<br>
            {{ $booking->stayRoom()?->title }}<br>
            {{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }} · {{ $booking->nights() }} {{ $booking->nights() === 1 ? 'night' : 'nights' }}<br>
            {{ \App\Models\Room::BOARD_PLANS[$booking->board] ?? 'Standard rate' }}{{ $booking->occupancy ? ' · '.ucfirst($booking->occupancy) : '' }}
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 18px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid #eee;">Room</td>
                    <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">{{ hotel_price($booking->total_amount, $setting ?? null, $currency) }}</td>
                </tr>
                @foreach($booking->charges as $charge)
                    <tr>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;">
                            {{ $charge->categoryLabel() }} · {{ $charge->name }}
                            @if($charge->included)
                                (included in the room)
                            @endif
                        </td>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">{{ $charge->included ? 'Included' : hotel_price($charge->line_total, $setting ?? null, $currency) }}</td>
                    </tr>
                @endforeach
                @if((float) ($booking->waived_amount ?? 0) >= 0.5)
                    <tr>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;">Complimentary</td>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">{{ hotel_price($booking->waived_amount, $setting ?? null, $currency) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:10px 0;">Paid</td>
                    <td style="padding:10px 0;text-align:right;">{{ hotel_price($booking->paid_amount, $setting ?? null, $currency) }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0 8px;"><strong>Balance</strong></td>
                    <td style="padding:4px 0 8px;text-align:right;"><strong>{{ hotel_price($booking->balance_amount, $setting ?? null, $currency) }}</strong></td>
                </tr>
            </table>
            @if($booking->payments->isNotEmpty())
                <div style="margin-top:8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;">Payments</div>
                @foreach($booking->payments as $payment)
                    <div style="font-size:13px;padding:4px 0;">{{ $payment->created_at->format('d M Y H:i') }} · {{ $payment->methodLabel() }} · {{ hotel_price($payment->amount, $setting ?? null, $currency) }}</div>
                @endforeach
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:18px 28px 26px;text-align:center;border-top:1px solid #e4ddd2;font-size:14px;line-height:1.5;">
            {{ $thanks }}
        </td>
    </tr>
</table>
