@php
    $parts = preg_split('/\s+/', trim((string) ($booking->names ?? '')), 2) ?: [];
    $firstName = old('first_name', $parts[0] ?? '');
    $lastName = old('last_name', $parts[1] ?? '');
    $selectedRoom = old('room_id', $booking->assigned_room_id ?: $booking->room_id);
@endphp
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Check in</label>
        <input type="date" name="checkin_date" class="form-control" value="{{ old('checkin_date', optional($booking->checkin_date)->toDateString()) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Check out</label>
        <input type="date" name="checkout_date" class="form-control" value="{{ old('checkout_date', optional($booking->checkout_date)->toDateString()) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Suite</label>
        <select name="room_id" id="desk-room" class="form-select" required>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}" @selected((int) $selectedRoom === (int) $room->id)>{{ $room->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Rooms</label>
        <input type="number" name="rooms" min="1" max="4" class="form-control" value="{{ old('rooms', $booking->rooms ?? 1) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Board</label>
        <select name="board" id="desk-board" class="form-select">
            <option value="">Standard rate</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Occupancy</label>
        <select name="occupancy" id="desk-occupancy" class="form-select">
            <option value="single">Single</option>
            <option value="double">Double</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Adults</label>
        <input type="number" name="adults" min="1" max="6" class="form-control" value="{{ old('adults', $booking->adults ?? 2) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Children</label>
        <input type="number" name="children" min="0" max="4" class="form-control" value="{{ old('children', $booking->children ?? 0) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Nightly</label>
        <input type="text" id="desk-nightly" class="form-control" value="" readonly>
    </div>
</div>
<h6 class="mt-4 mb-3">Primary contact</h6>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">First name</label>
        <input type="text" name="first_name" class="form-control" value="{{ $firstName }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Last name</label>
        <input type="text" name="last_name" class="form-control" value="{{ $lastName }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $booking->email) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $booking->phone) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Country</label>
        <input type="text" name="country" class="form-control" value="{{ old('country', $booking->country) }}">
    </div>
    <div class="col-md-5">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $booking->address) }}">
    </div>
    <div class="col-md-3">
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
    const roomSelect = document.getElementById('desk-room');
    const boardSelect = document.getElementById('desk-board');
    const occupancySelect = document.getElementById('desk-occupancy');
    const nightly = document.getElementById('desk-nightly');

    function fillBoards() {
        const room = deskRates[roomSelect.value] || { plans: [], price: 0 };
        const current = boardSelect.value || deskBoard || '';
        boardSelect.innerHTML = '<option value="">Standard rate</option>';
        room.plans.forEach(function (plan) {
            const option = document.createElement('option');
            option.value = plan.key;
            option.textContent = plan.label;
            option.selected = option.value === current;
            boardSelect.appendChild(option);
        });
        if ([...boardSelect.options].some(function (option) { return option.value === current; })) {
            boardSelect.value = current;
        }
        occupancySelect.value = deskOccupancy === 'single' ? 'single' : 'double';
        showNightly();
    }

    function showNightly() {
        const room = deskRates[roomSelect.value] || { plans: [], price: 0 };
        const plan = room.plans.find(function (item) { return item.key === boardSelect.value; });
        const amount = plan ? plan[occupancySelect.value] : room.price;
        nightly.value = amount ? Number(amount).toLocaleString() : '';
    }

    roomSelect.addEventListener('change', function () {
        boardSelect.value = '';
        fillBoards();
    });
    boardSelect.addEventListener('change', showNightly);
    occupancySelect.addEventListener('change', showNightly);
    fillBoards();
</script>
