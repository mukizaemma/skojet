<!DOCTYPE html>
<html lang="{{ $reservation->locale ?: 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reservation->reference }}</title>
    <style>
        body { margin: 0; background: #f6f1ea; color: #1a1a1a; font-family: Georgia, serif; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 28px 16px 48px; }
        .tools { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
        button, .tools a { font: inherit; background: #111; color: #fff; border: 0; padding: 0.55rem 0.9rem; cursor: pointer; text-decoration: none; }
        @media print { .tools { display: none; } body { background: #fff; } .wrap { padding: 0; } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="tools">
            <button type="button" onclick="window.print()">{{ site_trans('dining.bill_print') }}</button>
            <a href="{{ localized_route('dining.pass.pdf', ['token' => $reservation->access_token]) }}">{{ site_trans('dining.bill_pdf') }}</a>
        </div>
        @include('restaurant.bill-document', ['reservation' => $reservation])
    </div>
    @if(request()->boolean('print'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
