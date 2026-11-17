<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Restaurant reservation</title>
</head>
<body style="margin:0;padding:24px;background:#f6f1ea;">
    <p style="max-width:640px;margin:0 auto 16px;font-family:Georgia,serif;font-size:16px;line-height:1.5;color:#1a1a1a;">
        {{ $reservation->names }} sent a restaurant reservation and asked to be reached by {{ $reservation->submit_via === 'whatsapp' ? 'WhatsApp' : 'email' }}.
        <a href="{{ route('content-management.restaurant-reservations.show', $reservation) }}" style="color:#1a1a1a;">Open it in the hotel desk</a>.
    </p>
    @include('restaurant.bill-document', ['reservation' => $reservation])
</body>
</html>
