@php
    $invoice = $invoice ?? hotel_invoice();
    $granted = $reservation->accessGranted();
    $bar = $granted ? '#1f7a3a' : '#e2b100';
    $ink = $granted ? '#ffffff' : '#1a1a1a';
    $thanks = trim((string) ($invoice['footer'] ?? ''));
    if ($thanks === '') {
        $thanks = site_trans('dining.bill_thanks');
    }
    $contacts = collect([$invoice['phone'], $invoice['email']])->filter()->implode(' · ');
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e4ddd2;border-collapse:collapse;font-family:DejaVu Sans, Georgia, 'Times New Roman', serif;color:#1a1a1a;">
    <tr>
        <td style="padding:28px 28px 18px;text-align:center;border-bottom:1px solid #e4ddd2;">
            <div style="font-size:24px;line-height:1.2;">{{ $invoice['name'] }}</div>
            @if($invoice['address'] !== '')
                <div style="margin-top:8px;font-size:14px;line-height:1.5;">{{ $invoice['address'] }}</div>
            @endif
            @if($contacts !== '')
                <div style="margin-top:4px;font-size:14px;">{{ $contacts }}</div>
            @endif
            @if($invoice['tax_id'] !== '')
                <div style="margin-top:4px;font-size:13px;">{{ site_trans('dining.bill_tax', ['id' => $invoice['tax_id']]) }}</div>
            @endif
            <div style="margin-top:16px;font-size:12px;letter-spacing:0.18em;text-transform:uppercase;">{{ site_trans('dining.bill_title') }}</div>
            <div style="margin-top:4px;font-size:20px;">{{ $reservation->reference }}</div>
        </td>
    </tr>
    <tr>
        <td style="background:{{ $bar }};color:{{ $ink }};padding:16px 28px;">
            <div style="font-size:12px;letter-spacing:0.16em;text-transform:uppercase;">{{ site_trans('dining.access_label') }}</div>
            <div style="margin-top:4px;font-size:22px;">{{ $granted ? site_trans('dining.access_paid') : site_trans('dining.access_unpaid') }}</div>
            <div style="margin-top:6px;font-size:16px;">{{ site_trans('dining.bill_paid_amount', ['amount' => $reservation->money((float) $reservation->paid_amount)]) }}</div>
            <div style="margin-top:8px;font-size:14px;line-height:1.45;">{{ $granted ? site_trans('dining.access_enter') : site_trans('dining.access_denied') }}</div>
        </td>
    </tr>
    <tr>
        <td style="padding:22px 28px 8px;font-size:15px;line-height:1.5;">
            <strong>{{ $reservation->names }}</strong><br>
            {{ $reservation->phone }} · {{ $reservation->email }}<br>
            {{ optional($reservation->reserved_for)->format('D, M j, Y · H:i') }} · {{ site_trans('dining.bill_guests', ['count' => (string) $reservation->guests]) }}
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 18px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:15px;">
                @foreach($reservation->items as $item)
                    <tr>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;vertical-align:top;">
                            {{ $item->quantity }} × {{ $item->name }}
                            @if(filled($item->description))
                                <div style="color:#666;font-size:13px;margin-top:2px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap;vertical-align:top;">{{ $reservation->money((float) $item->line_total) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:12px 0 4px;"><strong>{{ site_trans('dining.reserve_total') }}</strong></td>
                    <td style="padding:12px 0 4px;text-align:right;"><strong>{{ $reservation->money((float) $reservation->total_amount) }}</strong></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:4px 28px 22px;font-size:14px;line-height:1.55;">
            <div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;margin-bottom:6px;">{{ site_trans('dining.momo_title') }}</div>
            <div>{{ site_trans('dining.bill_momo', ['code' => (string) $reservation->momo_code]) }}</div>
            @if(filled($reservation->momo_account_name))
                <div>{{ $reservation->momo_account_name }}</div>
            @endif
            @if($reservation->payment_choice !== 'later')
                <div>{{ site_trans('dining.bill_choice', ['choice' => $reservation->payment_choice === 'full' ? site_trans('dining.pay_full') : site_trans('dining.pay_minimum')]) }}</div>
                <div>{{ site_trans('dining.bill_claimed', ['amount' => $reservation->money((float) $reservation->claimed_amount)]) }}</div>
            @endif
            @if(filled($reservation->payer_name))
                <div>{{ site_trans('dining.bill_payer', ['name' => $reservation->payer_name]) }}</div>
            @endif
            @if(filled($reservation->notes))
                <div style="margin-top:10px;"><strong>{{ site_trans('dining.reserve_notes') }}</strong><br>{{ $reservation->notes }}</div>
            @endif
        </td>
    </tr>
    @if($reservation->passUrl())
        <tr>
            <td style="padding:0 28px 18px;font-size:14px;">
                <a href="{{ $reservation->passUrl() }}" style="color:#1a1a1a;">{{ site_trans('dining.bill_open_link') }}</a>
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding:18px 28px 26px;text-align:center;border-top:1px solid #e4ddd2;font-size:15px;line-height:1.5;">
            {{ $thanks }}
        </td>
    </tr>
</table>
