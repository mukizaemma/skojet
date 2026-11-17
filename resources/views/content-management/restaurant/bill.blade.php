@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
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

        @php
            $paidNow = (float) $reservation->paid_amount;
            $billTotal = (float) $reservation->total_amount;
            $due = max(0, round($billTotal - $paidNow, 2));
            $settled = $reservation->accessGranted();
            $suggestedPaid = $paidNow > 0
                ? $paidNow
                : ((float) $reservation->claimed_amount > 0 ? (float) $reservation->claimed_amount : $billTotal);
            $customOpen = filled(old('custom_name')) || filled(old('custom_price')) || $errors->has('custom_name');
            $guestBits = collect([$reservation->phone, $reservation->email])->filter();
            $billTotalInput = number_format($billTotal, 2, '.', '');
        @endphp

        <div class="pos-invoice no-print">
            <div class="pos-head">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.pos.invoices'), 'label' => 'Restaurant invoices'])
                    <h4>{{ $reservation->reference }}</h4>
                </div>
                @include('content-management.front-office.partials.pos-nav', ['current' => 'invoices'])
            </div>
            <div class="pos-invoice__bar">
                <p class="text-muted mb-0">{{ $reservation->names }} · {{ optional($reservation->reserved_for)->format('D, M j, Y · H:i') }} · {{ $reservation->guests }} {{ $reservation->guests === 1 ? 'guest' : 'guests' }}@if($guestBits->isNotEmpty()) · {{ $guestBits->implode(' · ') }}@endif</p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="pos-status {{ $settled ? 'is-paid' : 'is-due' }}">{{ $settled ? 'Paid' : 'Not paid' }}</span>
                    <button class="btn btn-dark" type="button" onclick="window.print()">Print</button>
                    <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-reservations.pdf', $reservation) }}">PDF</a>
                    <button class="btn btn-outline-dark" type="button" id="save-bill-image">Image</button>
                </div>
            </div>

            <div class="pos-invoice__grid">
                <section class="pos-ticket">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservation->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                        @if(filled($item->description))
                                            <div class="text-muted small">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ $reservation->money((float) $item->unit_price) }}</td>
                                    <td class="text-end">{{ $reservation->money((float) $item->line_total) }}</td>
                                    <td class="text-end">
                                        @if(\App\Support\DeskAudit::canRemove($item->created_by))
                                            <form method="POST" action="{{ route('content-management.restaurant-reservations.items.destroy', [$reservation, $item]) }}" onsubmit="return confirm('Remove this item from the bill?')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="confirm_remove" value="1">
                                                <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No items on this bill yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="pos-ticket__sum">
                        <div><span>Total</span><strong>{{ $reservation->money($billTotal) }}</strong></div>
                        <div><span>Paid</span><strong>{{ $reservation->money($paidNow) }}</strong></div>
                    </div>
                    <div class="pos-due"><span>Due</span><strong>{{ $reservation->money($due) }}</strong></div>
                    <form class="pos-ticket__add" method="POST" action="{{ route('content-management.restaurant-reservations.items.store', $reservation) }}">
                        @csrf
                        <label class="form-label" for="bill-menu">Add an item</label>
                        <div class="pos-ticket__add-row">
                            <select class="form-select" id="bill-menu" name="menu_item_id">
                                <option value="">Custom item</option>
                                @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $label)
                                    @php $group = $menu->where('category', $key); @endphp
                                    @if($group->isNotEmpty())
                                        <optgroup label="{{ $label }}">
                                            @foreach($group as $item)
                                                <option value="{{ $item->id }}" @selected((string) old('menu_item_id') === (string) $item->id)>{{ $item->name }} · {{ $reservation->money($item->priceFor($reservation->price_tier)) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            <input class="form-control" type="number" name="quantity" min="1" max="99" value="{{ old('quantity', 1) }}" required aria-label="Quantity">
                            <button class="btn btn-dark" type="submit">Add</button>
                        </div>
                        <div class="row g-2 mt-2" id="bill-custom" @unless($customOpen) hidden @endunless>
                            <div class="col-7">
                                <input class="form-control" type="text" name="custom_name" value="{{ old('custom_name') }}" maxlength="120" placeholder="Item name">
                            </div>
                            <div class="col-5">
                                <input class="form-control" type="number" name="custom_price" value="{{ old('custom_price') }}" min="0" step="0.01" placeholder="Price">
                            </div>
                        </div>
                    </form>
                </section>

                <aside class="pos-invoice__side">
                    <div class="pos-pay">
                        <h5 class="mb-3">Payment</h5>
                        @if(filled($reservation->momo_code) || filled($reservation->payer_name) || (float) $reservation->claimed_amount > 0)
                            <dl class="pos-pay__meta">
                                @if(filled($reservation->momo_code))
                                    <div><dt>MoMo</dt><dd>{{ $reservation->momo_code }}@if(filled($reservation->momo_account_name)) · {{ $reservation->momo_account_name }}@endif</dd></div>
                                @endif
                                @if((float) $reservation->claimed_amount > 0 || filled($reservation->payer_name))
                                    <div><dt>Guest said</dt><dd>{{ $reservation->payment_choice === 'full' ? 'Full bill' : 'Minimum' }} · {{ $reservation->money((float) $reservation->claimed_amount) }}@if(filled($reservation->payer_name)) · {{ $reservation->payer_name }}@endif</dd></div>
                                @endif
                            </dl>
                        @endif
                        <form method="POST" action="{{ route('content-management.restaurant-reservations.payment', $reservation) }}">
                            @csrf
                            <label class="form-label" for="paid_amount">Amount received</label>
                            <div class="pos-paid mb-3">
                                <input class="form-control" id="paid_amount" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', number_format($suggestedPaid, 2, '.', '')) }}">
                                <button class="btn btn-outline-dark" id="pay-total" type="button">Total</button>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-dark" type="submit" name="received" value="yes">Money received</button>
                                <button class="btn btn-outline-dark" type="submit" name="received" value="no">Money not received</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('content-management.restaurant-reservations.status', $reservation) }}" class="pos-pay__visit" onsubmit="return this.status.value !== 'cancelled' || confirm('Cancel this dining reservation?')">
                            @csrf
                            <label class="form-label" for="bill-status">Visit</label>
                            <input type="hidden" name="confirm_cancel" value="1">
                            <div class="pos-paid">
                                <select class="form-select" id="bill-status" name="status">
                                    @foreach(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                                        <option value="{{ $key }}" @selected($reservation->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-dark" type="submit">Save</button>
                            </div>
                        </form>
                    </div>

                    <div class="pos-share">
                        <h5 class="mb-3">Send</h5>
                        <label class="form-label" for="share-number">WhatsApp</label>
                        <div class="input-group mb-3">
                            <input class="form-control" id="share-number" type="tel" placeholder="2507…">
                            <button class="btn btn-dark" type="button" id="share-any">Open</button>
                        </div>
                        @if($reservation->isPaid())
                            @if($reservation->submit_via === 'email' && filled($reservation->email))
                                <form method="POST" action="{{ route('content-management.restaurant-reservations.share', $reservation) }}">
                                    @csrf
                                    <button class="btn btn-outline-dark w-100" type="submit">Email {{ $reservation->email }}</button>
                                </form>
                            @elseif(filled($reservation->phone))
                                <a class="btn btn-outline-dark w-100" id="share-guest" href="#">Guest WhatsApp</a>
                            @endif
                        @endif
                    </div>
                </aside>
            </div>
        </div>

        <article class="pos-invoice-print" id="restaurant-bill">
            @include('restaurant.bill-document', ['reservation' => $reservation])
        </article>
    </div>
</div>
<script type="application/json" id="bill-lines">@json($reservation->billLines())</script>
<script type="application/json" id="guest-bill-text">@json($guestBillText)</script>
<script>
    (function () {
        const menu = document.getElementById('bill-menu');
        const custom = document.getElementById('bill-custom');
        if (!menu || !custom) return;
        const paint = function () {
            custom.hidden = menu.value !== '';
        };
        if (menu.value === '' && custom.hidden) {
            const first = Array.from(menu.options).find(function (option) { return option.value !== ''; });
            if (first) menu.value = first.value;
        }
        menu.addEventListener('change', paint);
        paint();
        document.getElementById('pay-total')?.addEventListener('click', function () {
            const field = document.getElementById('paid_amount');
            if (field) field.value = @json($billTotalInput);
        });
    })();

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
@if(request()->boolean('print'))
<script>window.addEventListener('load', function () { window.print(); });</script>
@endif
@endsection
