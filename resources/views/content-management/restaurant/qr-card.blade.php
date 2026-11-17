<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserve — {{ $hotel }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f1eb; color: #111; font-family: Georgia, serif; }
        .card { width: min(420px, 92vw); background: #fff; text-align: center; padding: 2.5rem 1.75rem; }
        .card p { letter-spacing: 0.16em; text-transform: uppercase; font-size: 0.72rem; margin: 0 0 0.6rem; }
        h1 { font-weight: 500; font-size: 2rem; margin: 0 0 0.4rem; }
        .card span { display: block; margin-bottom: 1.25rem; }
        .card svg { width: 240px; height: 240px; }
        .url { margin-top: 1rem; font-size: 0.85rem; word-break: break-all; }
        .actions { margin-top: 1.25rem; }
        button { font: inherit; padding: 0.55rem 1rem; background: #111; color: #fff; border: 0; cursor: pointer; }
        @media print { .actions { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
    <article class="card">
        <p>{{ $hotel }}</p>
        <h1>Reserve</h1>
        <span>Scan to book a room or a table</span>
        {!! $svg !!}
        <div class="url">{{ $url }}</div>
        <div class="actions">
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </article>
</body>
</html>
