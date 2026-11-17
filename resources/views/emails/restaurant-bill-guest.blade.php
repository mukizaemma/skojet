<!DOCTYPE html>
<html lang="{{ $reservation->locale ?: 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ site_trans('dining.mail_bill_subject', ['hotel' => $hotelName, 'ref' => $reservation->reference, 'amount' => $reservation->money((float) $reservation->paid_amount)]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#f6f1ea;">
    <p style="max-width:640px;margin:0 auto 16px;font-family:Georgia,serif;font-size:16px;line-height:1.5;color:#1a1a1a;">{{ site_trans('dining.mail_bill_lead', ['hotel' => $hotelName, 'amount' => $reservation->money((float) $reservation->paid_amount)]) }}</p>
    @include('restaurant.bill-document', ['reservation' => $reservation])
</body>
</html>
