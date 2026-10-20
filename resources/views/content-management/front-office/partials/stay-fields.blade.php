@php
    $parts = preg_split('/\s+/', trim((string) ($booking->names ?? '')), 2) ?: [];
    $firstName = old('first_name', $parts[0] ?? '');
    $lastName = old('last_name', $parts[1] ?? '');
    $selectedRoom = old('room_id', $booking->assigned_room_id ?: $booking->room_id);
@endphp
@php
    $deskCheckin = old('checkin_date', optional($booking->checkin_date)->toDateString()) ?: now()->toDateString();
    $deskCheckout = old('checkout_date', optional($booking->checkout_date)->toDateString()) ?: now()->addDay()->toDateString();
@endphp
<div class="row g-3">
    <div class="col-12">
        <div class="desk-dates" id="desk-dates" data-checkin="{{ $deskCheckin }}" data-checkout="{{ $deskCheckout }}">
            <div class="desk-dates__fields">
                <button type="button" class="desk-dates__field" data-desk-dates-open>
                    <span>Check in</span>
                    <strong data-desk-checkin-label></strong>
                </button>
                <button type="button" class="desk-dates__field" data-desk-dates-open>
                    <span>Check out</span>
                    <strong data-desk-checkout-label></strong>
                </button>
            </div>
            <input type="hidden" name="checkin_date" id="desk-checkin" value="{{ $deskCheckin }}" required>
            <input type="hidden" name="checkout_date" id="desk-checkout" value="{{ $deskCheckout }}" required>
            <div class="desk-dates__popover" data-desk-popover hidden>
                <div class="desk-dates__months" data-desk-months></div>
                <div class="desk-dates__foot">
                    <p data-desk-nights></p>
                    <button type="button" class="btn btn-dark btn-sm" data-desk-dates-close>Done</button>
                </div>
            </div>
        </div>
    </div>
    @php
        $billCurrency = old('currency', $booking->currency ?: hotel_currency($setting ?? null));
        $currencyLocked = $booking->exists && ((float) $booking->paid_amount > 0 || (float) ($booking->charges_amount ?? 0) > 0 || (float) ($booking->waived_amount ?? 0) > 0 || $booking->charges->isNotEmpty());
    @endphp
    <div class="col-md-6">
        <label class="form-label" for="desk-currency">Invoice currency</label>
        <select name="currency" id="desk-currency" class="form-select" @disabled($currencyLocked) required>
            <option value="usd" @selected($billCurrency !== 'rwf')>USD ($)</option>
            <option value="rwf" @selected($billCurrency === 'rwf')>RWF</option>
        </select>
        @if($currencyLocked)
            <input type="hidden" name="currency" value="{{ $billCurrency === 'rwf' ? 'rwf' : 'usd' }}">
            <p class="text-muted small mb-0 mt-1">The currency stays fixed after a payment or an extra charge.</p>
        @else
            <p class="text-muted small mb-0 mt-1">The room and every extra on this bill use this currency.</p>
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label">Suite</label>
        <select name="room_id" id="desk-room" class="form-select" required>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}" @selected((int) $selectedRoom === (int) $room->id)>{{ $room->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Board</label>
        <select name="board" id="desk-board" class="form-select">
            <option value="">Standard rate</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Occupancy</label>
        <select name="occupancy" id="desk-occupancy" class="form-select">
            <option value="single">Single</option>
            <option value="double">Double</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Nightly</label>
        <input type="text" id="desk-nightly" class="form-control" value="" readonly>
    </div>
    <div class="col-md-6">
        <label class="form-label">Adults</label>
        <input type="number" name="adults" min="1" max="6" class="form-control" value="{{ old('adults', $booking->adults ?? 2) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Children</label>
        <input type="number" name="children" min="0" max="4" class="form-control" value="{{ old('children', $booking->children ?? 0) }}">
    </div>
    @php
        $deskChannel = old('reservation_channel', $booking->reservation_channel ?: ($booking->booking_type === 'online' ? 'website' : 'walkin'));
        $deskSource = old('guest_source', $booking->guest_source ?: 'unspecified');
    @endphp
    <div class="col-md-6">
        <label class="form-label" for="desk-channel">How it was reserved</label>
        <select name="reservation_channel" id="desk-channel" class="form-select" required>
            @foreach(\App\Models\Booking::CHANNELS as $value => $label)
                <option value="{{ $value }}" @selected($deskChannel === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="desk-source">Where the guest came from</label>
        <select name="guest_source" id="desk-source" class="form-select" required>
            @foreach(\App\Models\Booking::SOURCES as $value => $label)
                <option value="{{ $value }}" @selected($deskSource === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<h6 class="mt-4 mb-3">Primary contact</h6>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">First name</label>
        <input type="text" name="first_name" class="form-control" value="{{ $firstName }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Last name</label>
        <input type="text" name="last_name" class="form-control" value="{{ $lastName }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $booking->email) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $booking->phone) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Country</label>
        <input type="text" name="country" class="form-control" value="{{ old('country', $booking->country) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $booking->address) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Postal code</label>
        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $booking->postal_code) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Guest comments</label>
        <textarea name="message" class="form-control" rows="3">{{ old('message', $booking->message) }}</textarea>
    </div>
</div>
<script>
    const deskRates = @json($roomRates);
    const deskBoard = @json(old('board', $booking->board));
    const deskOccupancy = @json(old('occupancy', $booking->occupancy ?: 'double'));
    const currencySelect = document.getElementById('desk-currency');
    const roomSelect = document.getElementById('desk-room');
    const boardSelect = document.getElementById('desk-board');
    const occupancySelect = document.getElementById('desk-occupancy');
    const nightly = document.getElementById('desk-nightly');

    function activeCurrency() {
        return currencySelect && currencySelect.value === 'rwf' ? 'rwf' : 'usd';
    }

    function money(amount) {
        if (amount === null || amount === undefined || amount === '') {
            return '';
        }
        const number = Number(amount);
        if (!number) {
            return '';
        }
        const formatted = number.toLocaleString(undefined, { maximumFractionDigits: 0 });
        return activeCurrency() === 'rwf' ? 'RWF ' + formatted : '$' + formatted;
    }

    function roomAmount(room) {
        return activeCurrency() === 'rwf' ? room.price_rwf : room.price;
    }

    function planAmount(plan, occupancy) {
        return activeCurrency() === 'rwf' ? plan[occupancy + '_rwf'] : plan[occupancy];
    }

    function priced(label, amount) {
        const formatted = money(amount);
        return formatted ? label + ' · ' + formatted : label;
    }

    function fillBoards(applySavedOccupancy) {
        const room = deskRates[roomSelect.value] || { plans: [], price: 0 };
        const current = boardSelect.value || deskBoard || '';
        if (applySavedOccupancy) {
            occupancySelect.value = deskOccupancy === 'single' ? 'single' : 'double';
        }
        const occupancy = occupancySelect.value === 'single' ? 'single' : 'double';
        boardSelect.innerHTML = '';
        const standard = document.createElement('option');
        standard.value = '';
        standard.textContent = priced('Standard rate', roomAmount(room));
        boardSelect.appendChild(standard);
        room.plans.forEach(function (plan) {
            const amount = planAmount(plan, occupancy);
            if (amount === null || amount === undefined || amount === '') {
                return;
            }
            const option = document.createElement('option');
            option.value = plan.key;
            option.textContent = priced(plan.label, amount);
            boardSelect.appendChild(option);
        });
        if ([...boardSelect.options].some(function (option) { return option.value === current; })) {
            boardSelect.value = current;
        }
        showNightly();
    }

    function showNightly() {
        const room = deskRates[roomSelect.value] || { plans: [], price: 0 };
        const plan = room.plans.find(function (item) { return item.key === boardSelect.value; });
        const amount = plan ? planAmount(plan, occupancySelect.value) : roomAmount(room);
        nightly.value = money(amount);
    }

    roomSelect.addEventListener('change', function () {
        boardSelect.value = '';
        fillBoards(false);
    });
    boardSelect.addEventListener('change', showNightly);
    occupancySelect.addEventListener('change', function () {
        fillBoards(false);
    });
    if (currencySelect) {
        currencySelect.addEventListener('change', function () {
            fillBoards(false);
        });
    }
    fillBoards(true);

    (function () {
        const root = document.getElementById('desk-dates');
        if (!root) {
            return;
        }
        const checkinInput = document.getElementById('desk-checkin');
        const checkoutInput = document.getElementById('desk-checkout');
        const popover = root.querySelector('[data-desk-popover]');
        const monthsEl = root.querySelector('[data-desk-months]');
        const nightsEl = root.querySelector('[data-desk-nights]');
        const checkinLabel = root.querySelector('[data-desk-checkin-label]');
        const checkoutLabel = root.querySelector('[data-desk-checkout-label]');
        const weekdayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        let awaitingEnd = false;

        const parse = (iso) => {
            if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                return null;
            }
            const [year, month, day] = iso.split('-').map(Number);
            return new Date(year, month - 1, day);
        };
        const isoOf = (date) => {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return date.getFullYear() + '-' + month + '-' + day;
        };
        const labelOf = (iso) => parse(iso).toLocaleDateString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
        const addDays = (iso, days) => {
            const date = parse(iso);
            date.setDate(date.getDate() + days);
            return isoOf(date);
        };

        let checkin = checkinInput.value;
        let checkout = checkoutInput.value;
        if (!parse(checkin)) {
            checkin = isoOf(new Date());
        }
        if (!parse(checkout) || checkout <= checkin) {
            checkout = addDays(checkin, 1);
        }
        let cursor = parse(checkin);
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

        function paint() {
            checkinInput.value = checkin;
            checkoutInput.value = checkout;
            checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
            checkinLabel.textContent = labelOf(checkin);
            checkoutLabel.textContent = labelOf(checkout);
            const stay = Math.round((parse(checkout) - parse(checkin)) / 86400000);
            nightsEl.textContent = stay === 1 ? '1 night' : stay + ' nights';
            monthsEl.innerHTML = '';
            [0, 1].forEach((offset) => {
                const start = new Date(cursor.getFullYear(), cursor.getMonth() + offset, 1);
                const count = new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate();
                const month = document.createElement('div');
                month.className = 'desk-dates__month';
                const head = document.createElement('div');
                head.className = 'desk-dates__month-head';
                const prev = document.createElement('button');
                prev.type = 'button';
                prev.textContent = '‹';
                prev.setAttribute('aria-label', 'Previous month');
                if (offset === 0) {
                    prev.addEventListener('click', () => shift(-1));
                } else {
                    prev.hidden = true;
                }
                const title = document.createElement('strong');
                title.textContent = start.toLocaleString(undefined, { month: 'long', year: 'numeric' });
                const next = document.createElement('button');
                next.type = 'button';
                next.textContent = '›';
                next.setAttribute('aria-label', 'Next month');
                if (offset === 1) {
                    next.addEventListener('click', () => shift(1));
                } else {
                    next.hidden = true;
                }
                head.append(prev, title, next);
                const weeks = document.createElement('div');
                weeks.className = 'desk-dates__weekdays';
                weekdayLabels.forEach((name) => {
                    const cell = document.createElement('span');
                    cell.textContent = name;
                    weeks.append(cell);
                });
                const days = document.createElement('div');
                days.className = 'desk-dates__days';
                for (let i = 0; i < start.getDay(); i++) {
                    const empty = document.createElement('span');
                    empty.className = 'desk-dates__day is-empty';
                    days.append(empty);
                }
                for (let day = 1; day <= count; day++) {
                    const date = new Date(start.getFullYear(), start.getMonth(), day);
                    const iso = isoOf(date);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'desk-dates__day';
                    if (iso === checkin) {
                        button.classList.add('is-start');
                    } else if (iso === checkout) {
                        button.classList.add('is-end');
                    } else if (iso > checkin && iso < checkout) {
                        button.classList.add('is-range');
                    }
                    if (iso === isoOf(new Date())) {
                        button.classList.add('is-today');
                    }
                    button.textContent = String(day);
                    button.addEventListener('click', () => pick(iso));
                    days.append(button);
                }
                month.append(head, weeks, days);
                monthsEl.append(month);
            });
        }

        function shift(step) {
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + step, 1);
            paint();
        }

        function pick(iso) {
            if (!awaitingEnd || !checkin || iso <= checkin) {
                checkin = iso;
                checkout = addDays(iso, 1);
                awaitingEnd = true;
            } else {
                checkout = iso;
                awaitingEnd = false;
            }
            paint();
        }

        root.querySelectorAll('[data-desk-dates-open]').forEach((button) => {
            button.addEventListener('click', () => {
                popover.hidden = !popover.hidden;
                if (!popover.hidden) {
                    const start = parse(checkin);
                    cursor = new Date(start.getFullYear(), start.getMonth(), 1);
                    paint();
                }
            });
        });
        root.querySelector('[data-desk-dates-close]').addEventListener('click', () => {
            popover.hidden = true;
        });
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                popover.hidden = true;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                popover.hidden = true;
            }
        });
        paint();
    })();
</script>
