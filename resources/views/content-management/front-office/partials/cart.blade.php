@php
    $preview = $preview ?? null;
    $channel = old('reservation_channel', $booking->reservation_channel ?: ($booking->booking_type === 'online' ? 'website' : 'walkin'));
    $source = old('guest_source', $booking->guest_source ?: 'unspecified');
    $guest = trim((string) old('first_name', '').' '.(string) old('last_name', ''));
    if ($guest === '') {
        $guest = $booking->names ?: 'Guest';
    }
    $roomTitle = $preview['room'] ?? ($booking->stayRoom()?->title ?? '');
    $stay = $preview['stay'] ?? (($booking->checkin_date && $booking->checkout_date)
        ? $booking->checkin_date->format('d M Y').' – '.$booking->checkout_date->format('d M Y')
        : '');
    $nightsLabel = $preview['nights'] ?? ($booking->nights().' '.($booking->nights() === 1 ? 'night' : 'nights'));
    $boardLine = $preview['board'] ?? trim(((\App\Models\Room::BOARD_PLANS[$booking->board] ?? 'Standard rate')).($booking->occupancy ? ' · '.ucfirst($booking->occupancy) : ''));
    $nightly = $preview['nightly'] ?? '';
    $rack = $preview['rack'] ?? '';
    $hasDiscount = (bool) ($preview['has_discount'] ?? ((float) $booking->discount_amount >= 0.5));
    $billCurrency = $booking->currencyKey();
    $discount = $preview['discount'] ?? hotel_price($booking->discount_amount, $setting ?? null, $billCurrency);
    $discountLabel = $preview['discount_label'] ?? ($booking->discount_label ?: 'Discount');
    $chargesAmount = (float) ($booking->charges_amount ?? 0);
    $hasExtras = (bool) ($preview['has_extras'] ?? ($chargesAmount >= 0.5));
    $extras = $preview['extras'] ?? hotel_price($chargesAmount, $setting ?? null, $billCurrency);
    $total = $preview['total'] ?? hotel_price((float) $booking->total_amount + $chargesAmount, $setting ?? null, $billCurrency);
    $paid = $preview['paid'] ?? hotel_price($booking->paid_amount, $setting ?? null, $billCurrency);
    $balance = $preview['balance'] ?? hotel_price($booking->balance_amount, $setting ?? null, $billCurrency);
@endphp
<aside class="desk-cart" id="desk-cart" data-quote="{{ route('content-management.front-office.quote') }}" data-booking="{{ $booking->exists ? $booking->id : '' }}" data-refresh="{{ session()->hasOldInput() || ! $booking->exists ? '1' : '0' }}">
    <h6 class="mb-1">Invoice summary</h6>
    <p class="desk-cart__guest mb-1" data-cart-guest>{{ $guest }}</p>
    <p class="desk-cart__meta mb-3" data-cart-stay>{{ $stay }}</p>
    <div class="desk-cart__row"><span>Suite</span><strong data-cart-room>{{ $roomTitle }}</strong></div>
    <div class="desk-cart__row"><span>Board</span><strong data-cart-board>{{ $boardLine }}</strong></div>
    <div class="desk-cart__row"><span>Nights</span><strong data-cart-nights>{{ $nightsLabel }}</strong></div>
    <div class="desk-cart__row">
        <span>Nightly</span>
        <strong>
            <s class="desk-cart__rack" data-cart-rack @if($rack === '') hidden @endif>{{ $rack }}</s>
            <span data-cart-sale>{{ $nightly }}</span>
        </strong>
    </div>
    <div class="desk-cart__row" data-cart-discount-row @if(! $hasDiscount) hidden @endif>
        <span data-cart-discount-label>{{ $discountLabel }}</span>
        <strong data-cart-discount>{{ $discount }}</strong>
    </div>
    <div class="desk-cart__row" data-cart-extras-row @if(! $hasExtras) hidden @endif><span>Extras</span><strong data-cart-extras>{{ $extras }}</strong></div>
    <div class="desk-cart__row desk-cart__total"><span>Total</span><strong data-cart-total>{{ $total }}</strong></div>
    <div class="desk-cart__row"><span>Paid</span><strong data-cart-paid>{{ $paid }}</strong></div>
    <div class="desk-cart__row"><span>Balance</span><strong data-cart-balance>{{ $balance }}</strong></div>
    <p class="desk-cart__meta mt-3 mb-0">Reserved by <span data-cart-channel>{{ \App\Models\Booking::CHANNELS[$channel] ?? 'Walk-in' }}</span></p>
    <p class="desk-cart__meta mb-0">Came from <span data-cart-source>{{ \App\Models\Booking::SOURCES[$source] ?? 'Not specified' }}</span></p>
</aside>
<script>
    (function () {
        const cart = document.getElementById('desk-cart');
        const form = document.getElementById('desk-stay');
        if (!cart || !form) {
            return;
        }
        const token = document.querySelector('meta[name="csrf-token"]');
        const nightly = document.getElementById('desk-nightly');
        const channel = document.getElementById('desk-channel');
        const source = document.getElementById('desk-source');
        let timer = null;

        function text(selector, value) {
            const node = cart.querySelector(selector);
            if (node) {
                node.textContent = value || '';
            }
        }

        function apply(data) {
            text('[data-cart-guest]', data.guest);
            text('[data-cart-stay]', data.stay);
            text('[data-cart-room]', data.room);
            text('[data-cart-board]', data.board);
            text('[data-cart-nights]', data.nights);
            text('[data-cart-sale]', data.nightly);
            text('[data-cart-total]', data.total);
            text('[data-cart-paid]', data.paid);
            text('[data-cart-balance]', data.balance);
            text('[data-cart-discount]', data.discount);
            text('[data-cart-discount-label]', data.discount_label);
            text('[data-cart-extras]', data.extras);
            const extrasRow = cart.querySelector('[data-cart-extras-row]');
            if (extrasRow) {
                extrasRow.hidden = !data.has_extras;
            }
            const rack = cart.querySelector('[data-cart-rack]');
            if (rack) {
                rack.textContent = data.rack || '';
                rack.hidden = !data.rack;
            }
            const discountRow = cart.querySelector('[data-cart-discount-row]');
            if (discountRow) {
                discountRow.hidden = !data.has_discount;
            }
            if (nightly) {
                nightly.value = data.nightly || '';
            }
        }

        function syncSource() {
            const walkin = document.querySelector('[data-desk-walkin]');
            if (channel) {
                text('[data-cart-channel]', channel.options[channel.selectedIndex].text);
            }
            if (source) {
                text('[data-cart-source]', source.options[source.selectedIndex].text);
            }
            if (walkin && channel && !cart.dataset.booking) {
                walkin.hidden = channel.value !== 'walkin';
            }
        }

        function refresh() {
            const body = new FormData(form);
            if (cart.dataset.booking) {
                body.set('booking_id', cart.dataset.booking);
            }
            fetch(cart.dataset.quote, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token ? token.content : '',
                    'Accept': 'application/json',
                },
                body: body,
            }).then(function (response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            }).then(function (data) {
                if (data) {
                    apply(data);
                }
            }).catch(function () {});
        }

        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(refresh, 200);
        }

        form.addEventListener('change', schedule);
        form.addEventListener('input', function (event) {
            if (event.target && (event.target.name === 'first_name' || event.target.name === 'last_name')) {
                const first = form.querySelector('[name="first_name"]');
                const last = form.querySelector('[name="last_name"]');
                const name = ((first ? first.value : '') + ' ' + (last ? last.value : '')).trim();
                text('[data-cart-guest]', name || 'Guest');
            }
            schedule();
        });
        if (channel) {
            channel.addEventListener('change', syncSource);
        }
        if (source) {
            source.addEventListener('change', syncSource);
        }
        syncSource();
        document.addEventListener('DOMContentLoaded', syncSource);
        if (cart.dataset.refresh === '1') {
            schedule();
        }
    })();
</script>
