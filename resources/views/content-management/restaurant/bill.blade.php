@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<style>
    @media print {
        .sidebar, .navbar, .desk-top, .no-print { display: none !important; }
        .desk-app__body { padding: 0 !important; }
        #restaurant-bill { border: 0 !important; max-width: none !important; }
    }
</style>
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="no-print">
            @include('content-management.front-office.partials.alerts')
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3 no-print">
            <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-reservations') }}">All dining reservations</a>
            <button class="btn btn-dark" type="button" onclick="window.print()">Print</button>
            <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-reservations.pdf', $reservation) }}">Download PDF</a>
            <button class="btn btn-outline-dark" type="button" id="save-bill-image">Save image</button>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <article id="restaurant-bill">
                    @include('restaurant.bill-document', ['reservation' => $reservation])
                </article>
            </div>

            <div class="col-lg-5 no-print">
                <div class="bg-light rounded p-4 mb-3">
                    <h5>Payment received</h5>
                    <p class="mb-1">Guest says they paid the {{ $reservation->payment_choice === 'full' ? 'full bill' : 'minimum' }}: <strong>{{ $reservation->money((float) $reservation->claimed_amount) }}</strong></p>
                    <p class="mb-1">Name on the MoMo payment: <strong>{{ $reservation->payer_name ?: '—' }}</strong></p>
                    <p class="mb-3">MoMo code: <strong>{{ $reservation->momo_code ?: '—' }}</strong>@if(filled($reservation->momo_account_name)) · {{ $reservation->momo_account_name }}@endif</p>
                    <form method="POST" action="{{ route('content-management.restaurant-reservations.payment', $reservation) }}">
                        @csrf
                        <label class="form-label" for="paid_amount">Amount received</label>
                        <input class="form-control mb-2" id="paid_amount" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', number_format((float) ($reservation->paid_amount > 0 ? $reservation->paid_amount : $reservation->claimed_amount), 2, '.', '')) }}">
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-dark" type="submit" name="received" value="yes">Money received</button>
                            <button class="btn btn-outline-dark" type="submit" name="received" value="no">Money not received</button>
                        </div>
                        <p class="text-muted small mt-2 mb-0">The guest is emailed this bill with the updated paid amount. Not received sets the paid amount to zero and keeps access yellow.</p>
                    </form>
                    <form method="POST" action="{{ route('content-management.restaurant-reservations.status', $reservation) }}" class="row g-2 align-items-end mt-3">
                        @csrf
                        <div class="col">
                            <label class="form-label">Visit</label>
                            <select class="form-select" name="status">
                                @foreach(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                                    <option value="{{ $key }}" @selected($reservation->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-dark" type="submit">Save</button>
                        </div>
                    </form>
                </div>

                <div class="bg-light rounded p-4 mb-3">
                    <h5>Items</h5>
                    <ul class="list-unstyled mb-3">
                        @forelse($reservation->items as $item)
                            <li class="d-flex justify-content-between align-items-center gap-2 py-1">
                                <span>{{ $item->quantity }} × {{ $item->name }}</span>
                                <form method="POST" action="{{ route('content-management.restaurant-reservations.items.destroy', [$reservation, $item]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                                </form>
                            </li>
                        @empty
                            <li class="text-muted">No items on this bill yet.</li>
                        @endforelse
                    </ul>
                    <h5>Add to the bill</h5>
                    <form method="POST" action="{{ route('content-management.restaurant-reservations.items.store', $reservation) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Menu item</label>
                            <select class="form-select" name="menu_item_id">
                                <option value="">Custom item</option>
                                @foreach($menu as $item)
                                    <option value="{{ $item->id }}">{{ $item->categoryLabel() }} · {{ $item->name }} · {{ $reservation->money($item->priceFor($reservation->price_tier)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-7">
                            <label class="form-label">Custom name</label>
                            <input class="form-control" type="text" name="custom_name" maxlength="120" placeholder="Only if it is not on the menu">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Custom price</label>
                            <input class="form-control" type="number" name="custom_price" min="0" step="0.01">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Quantity</label>
                            <input class="form-control" type="number" name="quantity" min="1" max="99" value="1" required>
                        </div>
                        <div class="col-8 d-flex align-items-end">
                            <button class="btn btn-dark" type="submit">Add item</button>
                        </div>
                    </form>
                </div>

                <div class="bg-light rounded p-4">
                    <h5>Share this bill</h5>
                    <label class="form-label" for="share-number">Any WhatsApp number</label>
                    <div class="input-group mb-3">
                        <input class="form-control" id="share-number" type="tel" placeholder="2507…">
                        <button class="btn btn-dark" type="button" id="share-any">Open WhatsApp</button>
                    </div>

                    @if($reservation->isPaid())
                        @if($reservation->submit_via === 'email')
                            <form method="POST" action="{{ route('content-management.restaurant-reservations.share', $reservation) }}">
                                @csrf
                                <button class="btn btn-outline-dark" type="submit">Email the paid bill to {{ $reservation->email }}</button>
                            </form>
                        @else
                            <a class="btn btn-outline-dark" id="share-guest" href="#">Send the paid bill on the guest’s WhatsApp</a>
                        @endif
                    @else
                        <p class="text-muted mb-0">The guest asked for {{ $reservation->submit_via === 'whatsapp' ? 'WhatsApp' : 'email' }}. That send is available after you mark the bill as paid.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="bill-lines">@json($reservation->billLines())</script>
<script type="application/json" id="guest-bill-text">@json($guestBillText)</script>
<script>
    document.getElementById('save-bill-image')?.addEventListener('click', function () {
        const lines = JSON.parse(document.getElementById('bill-lines').textContent || '[]');
        const canvas = document.createElement('canvas');
        const width = 900;
        const lineHeight = 34;
        canvas.width = width;
        canvas.height = 90 + (lines.length * lineHeight);
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#111111';
        context.font = '22px Georgia, serif';
        lines.forEach(function (line, index) {
            context.fillText(String(line).slice(0, 78), 40, 56 + (index * lineHeight));
        });
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = @json($reservation->reference.'-bill.png');
        link.click();
    });

    function openWhatsapp(number, text) {
        const digits = String(number || '').replace(/\D+/g, '');
        if (!digits) {
            return;
        }
        window.open('https://wa.me/' + digits + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
    }

    document.getElementById('share-any')?.addEventListener('click', function () {
        const text = JSON.parse(document.getElementById('guest-bill-text').textContent || '""');
        openWhatsapp(document.getElementById('share-number').value, text);
    });

    document.getElementById('share-guest')?.addEventListener('click', function (event) {
        event.preventDefault();
        const text = JSON.parse(document.getElementById('guest-bill-text').textContent || '""');
        openWhatsapp(@json(whatsapp_digits($reservation->phone)), text);
    });
</script>
@endsection
