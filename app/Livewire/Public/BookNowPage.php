<?php

namespace App\Livewire\Public;

use App\Models\Room;
use App\Models\RoomDiscount;
use App\Models\Setting;
use App\Rules\ActiveEmail;
use App\Services\BookingSubmissionService;
use App\Services\RoomAvailabilityService;
use App\Services\RoomDiscountService;
use App\Support\BookingStayReasons;
use App\Support\HotelChannels;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.frontbase')]
class BookNowPage extends Component
{
    /** @var int */
    public $step = 1;

    /** @var int|string|null */
    public $room_id = null;

    /** @var string|null */
    public $checkin = '';

    /** @var string|null */
    public $checkout = '';

    /** @var string */
    public $party_type = 'individual';

    /** @var int|string|null */
    public $adults = 2;

    /** @var int|string|null */
    public $children = 0;

    /** @var int|string|null */
    public $room_count = 1;

    /** @var string|null */
    public $names = '';

    /** @var string|null */
    public $phone = '';

    /** @var string|null */
    public $email = '';

    /** @var string|null */
    public $first_name = '';

    /** @var string|null */
    public $last_name = '';

    /** @var string|null */
    public $address = '';

    /** @var string|null */
    public $city = '';

    /** @var string|null */
    public $postal_code = '';

    /** @var string|null */
    public $country = '';

    /** @var string|null */
    public $stay_reason = '';

    /** @var string|null */
    public $message = '';

    /** @var string */
    public $confirm_via = 'email';

    /** @var bool */
    public $agree_terms = false;

    /** @var bool */
    public $submitted = false;

    /** @var int|null */
    public $submittedBookingId = null;

    /** @var bool */
    public $guestEmailSent = false;

    /** @var string|null */
    public $whatsappUrl = null;

    /** @var bool Guest chose a suite; otherwise the cheapest (or discounted) rate stays selected. */
    public $roomPicked = false;

    /** @var string Board plan key, such as half_board. */
    public $board = '';

    /** @var string single or double occupancy. */
    public $occupancy = '';

    /** @var bool Guest chose a meal plan. Nothing is selected until they do. */
    public $mealPicked = false;

    public function mount(): void
    {
        if (hotel_book_now_is_external()) {
            $this->redirect(hotel_book_now_url(), navigate: false);

            return;
        }

        $saved = session($this->staySessionKey());
        if (is_array($saved) && filled($saved['checkin'] ?? null) && filled($saved['checkout'] ?? null)) {
            $this->checkin = (string) $saved['checkin'];
            $this->checkout = (string) $saved['checkout'];
            $this->adults = (int) ($saved['adults'] ?? 2);
            $this->children = (int) ($saved['children'] ?? 0);
            $this->room_count = (int) ($saved['room_count'] ?? 1);
            $this->party_type = (string) ($saved['party_type'] ?? 'individual');
            $this->room_id = $saved['room_id'] ?? null;
            $this->roomPicked = (bool) ($saved['room_picked'] ?? false);
            $this->mealPicked = (bool) ($saved['meal_picked'] ?? false);
            $this->board = $this->mealPicked ? (string) ($saved['board'] ?? '') : '';
            $this->occupancy = $this->mealPicked ? (string) ($saved['occupancy'] ?? '') : '';
            $savedStep = (int) ($saved['step'] ?? 1);
            if (in_array($savedStep, [1, 2], true)) {
                $this->step = $savedStep;
            }
        } else {
            $this->checkin = now()->toDateString();
            $this->checkout = now()->addDay()->toDateString();
        }

        $preselect = (int) request()->query('room', 0);
        if ($preselect > 0 && Room::query()->where('id', $preselect)->where('status', 'Active')->exists()) {
            $this->room_id = $preselect;
            $this->roomPicked = true;
        }

        if (auth()->check()) {
            $user = auth()->user();
            $this->names = trim((string) ($user->name ?? ''));
            $this->email = trim((string) ($user->email ?? ''));
            $parts = preg_split('/\s+/', $this->names, 2) ?: [];
            $this->first_name = $parts[0] ?? '';
            $this->last_name = $parts[1] ?? '';
        }

        $this->normalizeStayInput();
        if (! $this->roomPicked || ! $this->selectedRoom()) {
            $this->roomPicked = false;
            $this->applyDefaultRoom();
        }
        $this->alignRate();
        $this->rememberStay();
    }

    public function updated($property): void
    {
        if (in_array($property, ['room_id', 'checkin', 'checkout', 'adults', 'children', 'room_count', 'party_type'], true)) {
            $this->normalizeStayInput();
            if (! $this->roomPicked) {
                $this->applyDefaultRoom();
            }
            $this->alignRate();
            $this->rememberStay();
            $this->syncLimitFeedback();
        }

        if (in_array($property, ['names', 'first_name', 'last_name', 'phone', 'email', 'country', 'address', 'city', 'postal_code', 'stay_reason', 'message'], true)) {
            $this->{$property} = (string) ($this->{$property} ?? '');
        }
    }

    public function goToStep(int $step): void
    {
        if ($this->submitted) {
            return;
        }

        if ($step < 1 || $step > 3) {
            return;
        }

        if ($step > $this->step) {
            if ($step >= 2) {
                $this->validateStay();
            }
            if ($step >= 3) {
                $this->validateGuest();
            }
        }

        $this->step = $step;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateStay();
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->validateGuest();
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->submitted) {
            return;
        }

        $this->step = max(1, $this->step - 1);
    }

    public function selectRoom(int $roomId): void
    {
        $this->chooseRoom($roomId);
        $this->validateStay();
        $this->step = 2;
        $this->rememberStay();
    }

    public function chooseRoom(int $roomId): void
    {
        if (! Room::query()->where('id', $roomId)->where('status', 'Active')->exists()) {
            return;
        }

        $this->room_id = $roomId;
        $this->roomPicked = true;
        $this->normalizeStayInput();
        $this->alignRate();
        $this->rememberStay();
        $this->syncLimitFeedback();
    }

    public function chooseRate(int $roomId, string $board, string $occupancy): void
    {
        $room = Room::query()->where('id', $roomId)->where('status', 'Active')->first();
        if (! $room || ! $room->rateCell($board, $occupancy)) {
            return;
        }

        $this->room_id = $roomId;
        $this->roomPicked = true;
        $this->mealPicked = true;
        $this->board = $board;
        $this->occupancy = $occupancy;
        $this->normalizeStayInput();
        $this->rememberStay();
        $this->syncLimitFeedback();
    }

    public function selectMealPlan(string $board, string $occupancy = ''): void
    {
        if (! isset(Room::BOARD_PLANS[$board]) || ! $this->selectedRoom()) {
            return;
        }

        $this->mealPicked = true;
        $this->board = $board;
        $this->occupancy = in_array($occupancy, ['single', 'double'], true) ? $occupancy : '';
        $this->alignRate();
        $this->rememberStay();
    }

    public function setStayDates(string $checkin, string $checkout): void
    {
        $this->checkin = $checkin;
        $this->checkout = $checkout;
        $this->normalizeStayInput();
        if (! $this->roomPicked) {
            $this->applyDefaultRoom();
        }
        $this->rememberStay();
        $this->syncLimitFeedback();
    }

    public function setParty(string $party): void
    {
        $this->party_type = $party === 'group' ? 'group' : 'individual';
        if ($this->party_type === 'individual') {
            $this->room_count = 1;
        }

        $this->normalizeStayInput();
        if (! $this->roomPicked) {
            $this->applyDefaultRoom();
        }
        $this->rememberStay();
        $this->syncLimitFeedback();
    }

    public function adjustGuests(string $field, int $delta): void
    {
        if ($field === 'adults') {
            $this->adults = (int) $this->adults + $delta;
        } elseif ($field === 'children') {
            $this->children = (int) $this->children + $delta;
        } elseif ($field === 'room_count') {
            $this->room_count = (int) $this->room_count + $delta;
        } else {
            return;
        }

        $this->normalizeStayInput();
        if (! $this->roomPicked) {
            $this->applyDefaultRoom();
        }
        $this->rememberStay();
        $this->syncLimitFeedback();
    }

    public function cancelStay(): void
    {
        session()->forget($this->staySessionKey());
        $this->step = 1;
        $this->roomPicked = false;
        $this->checkin = now()->toDateString();
        $this->checkout = now()->addDay()->toDateString();
        $this->adults = 2;
        $this->children = 0;
        $this->room_count = 1;
        $this->room_id = null;
        $this->board = '';
        $this->occupancy = '';
        $this->mealPicked = false;
        $this->normalizeStayInput();
        $this->applyDefaultRoom();
        $this->alignRate();
        $this->rememberStay();
        $this->resetErrorBag();
    }

    public function backToRooms(): void
    {
        if ($this->submitted) {
            return;
        }

        $this->step = 1;
        $this->rememberStay();
    }

    public function submitReservation(string $via, BookingSubmissionService $bookings): void
    {
        $this->confirm_via = $via === 'whatsapp' ? 'whatsapp' : 'email';
        $this->confirmBooking($bookings);
    }

    public function confirmBooking(BookingSubmissionService $bookings): void
    {
        $this->validateStay();
        $this->validateGuest();
        $this->validateMealPlan();
        $this->validate([
            'confirm_via' => 'required|in:email,whatsapp',
            'agree_terms' => 'accepted',
        ], [
            'agree_terms.accepted' => site_trans('booking.wizard_terms_required'),
        ]);

        $this->names = trim($this->first_name.' '.$this->last_name);
        $this->alignRate();
        $rateNote = $this->rateNote();
        $notes = array_values(array_filter([
            $rateNote,
            filled($this->address) ? 'Address: '.$this->address : null,
            filled($this->postal_code) ? 'Postal code: '.$this->postal_code : null,
            filled($this->message) ? trim((string) $this->message) : null,
            'Confirmation preference: '.$this->confirm_via,
        ]));

        try {
            $request = Request::create('/booking', 'POST', [
                'room_id' => $this->room_id,
                'checkin' => $this->checkin,
                'checkout' => $this->checkout,
                'adults' => $this->adults,
                'children' => $this->children,
                'rooms' => $this->room_count,
                'names' => $this->names,
                'phone' => $this->phone,
                'email' => $this->email,
                'country' => $this->country,
                'stay_reason' => $this->stay_reason,
                'party_type' => $this->party_type,
                'board' => $this->board,
                'occupancy' => $this->occupancy,
                'message' => implode("\n", $notes),
            ]);

            $result = $bookings->submit($request);
            $this->submittedBookingId = $result['booking']->id;
            $this->guestEmailSent = (bool) ($result['guest_sent'] ?? false);
            $this->submitted = true;
            $this->step = 3;
            $whatsappUrl = null;
            if ($this->confirm_via === 'whatsapp') {
                $whatsappUrl = $this->whatsappDeepLink($result['booking']->id, $result['booking']);
                $this->whatsappUrl = $whatsappUrl;
            }
            $this->dispatch(
                'reservation-submitted',
                via: $this->confirm_via,
                whatsappUrl: $whatsappUrl,
                reference: $this->submittedBookingId,
                guestSent: (bool) ($result['guest_sent'] ?? false),
                adminSent: (bool) ($result['admin_sent'] ?? false),
            );
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function availability(): RoomAvailabilityService
    {
        return app(RoomAvailabilityService::class);
    }

    /**
     * @return array{inventory: int, available: int, held: int, max_occupancy: int, max_guests_for_request: int, dates_ready: bool}|null
     */
    protected function staySummary(): ?array
    {
        $room = $this->selectedRoom();
        if (! $room) {
            return null;
        }

        return $this->availability()->summary($room, (string) $this->checkin, (string) $this->checkout);
    }

    protected function normalizeStayInput(): void
    {
        $this->checkin = (string) ($this->checkin ?? '');
        $this->checkout = (string) ($this->checkout ?? '');
        $this->party_type = in_array($this->party_type, ['individual', 'group'], true)
            ? $this->party_type
            : 'individual';

        if ($this->room_id === '' || $this->room_id === null) {
            $this->room_id = null;
        } else {
            $this->room_id = (int) $this->room_id;
        }

        $isGroup = $this->party_type === 'group';
        $this->adults = max(1, (int) ($this->adults ?? ($isGroup ? 4 : 2)));
        $this->children = max(0, (int) ($this->children ?? 0));
        $this->room_count = max(1, (int) ($this->room_count ?? 1));
        if ($this->room_count > 1) {
            $this->party_type = 'group';
            $isGroup = true;
        }

        // Soft ceilings only (party size), not room inventory — over-limit values keep
        // the typed number so we can show red labels / explanations instead of silently clamping.
        $maxAdults = $isGroup ? 120 : 20;
        $maxChildren = $isGroup ? 60 : 20;
        $this->adults = min($maxAdults, (int) $this->adults);
        $this->children = min($maxChildren, (int) $this->children);
        $this->room_count = min($isGroup ? 200 : 20, (int) $this->room_count);

        try {
            $today = new DateTime(now()->toDateString());
            $checkin = new DateTime($this->checkin !== '' ? $this->checkin : now()->toDateString());
            if ($checkin < $today) {
                $checkin = clone $today;
            }
            $checkout = new DateTime($this->checkout !== '' ? $this->checkout : now()->addDay()->toDateString());
            if ($checkout <= $checkin) {
                $checkout = (clone $checkin)->modify('+1 day');
            }
            $this->checkin = $checkin->format('Y-m-d');
            $this->checkout = $checkout->format('Y-m-d');
        } catch (\Throwable) {
            $this->checkin = now()->toDateString();
            $this->checkout = now()->addDay()->toDateString();
        }
    }

    protected function staySessionKey(): string
    {
        return 'skojet.book_stay';
    }

    protected function rememberStay(): void
    {
        session([$this->staySessionKey() => [
            'checkin' => $this->checkin,
            'checkout' => $this->checkout,
            'adults' => (int) $this->adults,
            'children' => (int) $this->children,
            'room_count' => (int) $this->room_count,
            'party_type' => (string) $this->party_type,
            'room_id' => $this->room_id,
            'room_picked' => (bool) $this->roomPicked,
            'board' => (string) $this->board,
            'occupancy' => (string) $this->occupancy,
            'meal_picked' => (bool) $this->mealPicked,
            'step' => (int) $this->step,
        ]]);
    }

    protected function applyDefaultRoom(): void
    {
        $this->room_id = $this->cheapestRoomId();
        $this->alignRate();
    }

    protected function alignRate(): void
    {
        $room = $this->selectedRoom();
        $currency = hotel_currency();
        if (! $room || $room->publicRatePlans($currency) === []) {
            $this->board = '';
            $this->occupancy = '';

            return;
        }

        if (! $this->mealPicked || ! isset(Room::BOARD_PLANS[$this->board])) {
            $this->board = '';
            $this->occupancy = '';

            return;
        }

        $kept = $room->rateCell($this->board, $this->occupancy, $currency);
        if ($kept) {
            $this->board = $kept['board'];
            $this->occupancy = $kept['occupancy'];

            return;
        }

        $prefer = (int) $this->adults <= 1 ? 'single' : 'double';
        $onPlan = $room->rateCell($this->board, $prefer, $currency)
            ?? $room->rateCell($this->board, $prefer === 'single' ? 'double' : 'single', $currency);
        if ($onPlan) {
            $this->board = $onPlan['board'];
            $this->occupancy = $onPlan['occupancy'];

            return;
        }

        $this->mealPicked = false;
        $this->board = '';
        $this->occupancy = '';
    }

    protected function validateMealPlan(): void
    {
        $room = $this->selectedRoom();
        if (! $room || $room->publicRatePlans(hotel_currency()) === []) {
            return;
        }

        $this->validate([
            'board' => 'required|in:'.implode(',', array_keys(Room::BOARD_PLANS)),
            'occupancy' => 'required|in:single,double',
        ], [
            'board.required' => site_trans('booking.channel_meal_required'),
            'board.in' => site_trans('booking.channel_meal_required'),
            'occupancy.required' => site_trans('booking.channel_meal_required'),
            'occupancy.in' => site_trans('booking.channel_meal_required'),
        ]);
    }

    protected function rateNote(): ?string
    {
        if (! isset(Room::BOARD_PLANS[$this->board]) || ! in_array($this->occupancy, ['single', 'double'], true)) {
            return null;
        }

        return 'Rate: '.site_trans('room.board_'.$this->board).', '.site_trans('room.occ_'.$this->occupancy.'_long');
    }

    protected function cheapestRoomId(): ?int
    {
        $rooms = Room::query()->where('status', 'Active')->get();
        $bestId = null;
        $bestTotal = null;
        $bestDiscounted = false;
        $bestAvailable = false;
        $party = (int) $this->adults + (int) $this->children;
        $roomsRequested = max(1, (int) $this->room_count);

        foreach ($rooms as $room) {
            $quote = app(RoomDiscountService::class)->quote(
                $room,
                (string) $this->checkin,
                (string) $this->checkout,
                (int) $this->adults,
                (int) $this->children,
                $roomsRequested,
                0,
                $this->board !== '' ? $this->board : null,
                $this->occupancy !== '' ? $this->occupancy : null
            );
            $summary = $this->availability()->summary($room, (string) $this->checkin, (string) $this->checkout);
            $sleeps = max(1, (int) ($room->max_occupancy ?? 2)) * $roomsRequested;
            $available = ($summary['dates_ready'] ?? false)
                && (int) ($summary['available'] ?? 0) >= $roomsRequested
                && $party <= $sleeps;
            $total = (float) ($quote['total'] ?? 0);
            $discounted = (bool) ($quote['has_discount'] ?? false);
            $better = $bestId === null
                || ($available && ! $bestAvailable)
                || ($available === $bestAvailable && (
                    $total < $bestTotal
                    || ($total == $bestTotal && $discounted && ! $bestDiscounted)
                ));

            if ($better) {
                $bestId = (int) $room->id;
                $bestTotal = $total;
                $bestDiscounted = $discounted;
                $bestAvailable = $available;
            }
        }

        return $bestId;
    }

    /**
     * Surface limit explanations only when the guest exceeds inventory or occupancy.
     */
    protected function syncLimitFeedback(): void
    {
        $this->resetErrorBag(['adults', 'children', 'room_count']);

        $room = $this->selectedRoom();
        if (! $room) {
            return;
        }

        $summary = $this->availability()->summary($room, (string) $this->checkin, (string) $this->checkout);
        $available = max(0, (int) $summary['available']);
        $inventory = max(1, (int) $summary['inventory']);
        $maxOccupancy = max(1, (int) $summary['max_occupancy']);
        $roomCount = max(1, (int) $this->room_count);
        $guests = (int) $this->adults + (int) $this->children;
        $capacity = $maxOccupancy * $roomCount;

        if (($summary['dates_ready'] ?? false) && $available < 1) {
            $this->addError('room_count', site_trans('booking.wizard_dates_unavailable'));

            return;
        }

        if (($summary['dates_ready'] ?? false) && $roomCount > $available) {
            $this->addError('room_count', site_trans('booking.wizard_rooms_dates_inventory', [
                'max' => $available,
                'inventory' => $inventory,
            ]));
        }

        if ($guests > $capacity) {
            $needed = $this->availability()->minRoomsForGuests($room, (int) $this->adults, (int) $this->children);
            $this->addError('adults', site_trans('booking.wizard_capacity_need_rooms', [
                'max' => $capacity,
                'needed' => $needed,
                'per_room' => $maxOccupancy,
            ]));
        }
    }

    protected function validateStay(): void
    {
        $this->normalizeStayInput();
        $this->syncLimitFeedback();

        $summary = $this->staySummary();
        $available = max(0, (int) ($summary['available'] ?? 0));
        $isGroup = $this->party_type === 'group';
        $maxAdults = $isGroup ? 120 : 20;
        $maxChildren = $isGroup ? 60 : 20;
        $maxRoomsRule = max(1, $available > 0 ? $available : 1);

        $this->validate([
            'party_type' => 'required|in:individual,group',
            'room_id' => 'required|exists:rooms,id',
            'checkin' => 'required|date|after_or_equal:today',
            'checkout' => 'required|date|after:checkin',
            'adults' => 'required|integer|min:1|max:'.$maxAdults,
            'children' => 'nullable|integer|min:0|max:'.$maxChildren,
            'room_count' => 'required|integer|min:1|max:'.$maxRoomsRule,
        ], [
            'room_count.max' => site_trans('booking.wizard_rooms_dates_inventory', [
                'max' => $available,
                'inventory' => (int) ($summary['inventory'] ?? $available),
            ]),
        ]);

        $room = Room::find($this->room_id);
        if ($room && $room->status !== 'Active') {
            throw ValidationException::withMessages([
                'room_id' => site_trans('booking.wizard_room_unavailable'),
            ]);
        }

        if ($room) {
            $this->availability()->assertRequestFits(
                $room,
                (string) $this->checkin,
                (string) $this->checkout,
                (int) $this->room_count,
                (int) $this->adults,
                (int) $this->children
            );
        }
    }

    protected function validateGuest(): void
    {
        $this->first_name = trim((string) ($this->first_name ?? ''));
        $this->last_name = trim((string) ($this->last_name ?? ''));
        $this->names = trim($this->first_name.' '.$this->last_name);
        $this->phone = (string) ($this->phone ?? '');
        $this->email = (string) ($this->email ?? '');
        $this->country = (string) ($this->country ?? '');
        $this->address = (string) ($this->address ?? '');
        $this->city = (string) ($this->city ?? '');
        $this->postal_code = (string) ($this->postal_code ?? '');
        $this->message = (string) ($this->message ?? '');

        $this->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'names' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => ['required', 'email', 'max:255', new ActiveEmail],
            'country' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:1000',
        ]);
    }

    public function selectedRoom(): ?Room
    {
        if (! $this->room_id) {
            return null;
        }

        return Room::query()->find($this->room_id);
    }

    public function nights(): int
    {
        try {
            $checkin = new DateTime((string) $this->checkin);
            $checkout = new DateTime((string) $this->checkout);
            $diff = (int) $checkin->diff($checkout)->days;

            return max(0, $diff);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{nights: int, nightly: float, sale_nightly: float, list_total: float, discount_total: float, total: float, label: string, has_discount: bool}
     */
    public function quoteFor(?Room $room): array
    {
        if (! $room) {
            return [
                'nights' => $this->nights(),
                'nightly' => 0.0,
                'sale_nightly' => 0.0,
                'list_total' => 0.0,
                'discount_total' => 0.0,
                'total' => 0.0,
                'label' => '',
                'has_discount' => false,
            ];
        }

        return app(RoomDiscountService::class)->quote(
            $room,
            (string) $this->checkin,
            (string) $this->checkout,
            (int) $this->adults,
            (int) $this->children,
            max(1, (int) $this->room_count),
            0,
            $this->board !== '' ? $this->board : null,
            $this->occupancy !== '' ? $this->occupancy : null
        );
    }

    public function estimatedTotal(): float
    {
        return (float) $this->quoteFor($this->selectedRoom())['total'];
    }

    protected function whatsappDeepLink(?int $bookingId = null, $booking = null): ?string
    {
        $channels = HotelChannels::all();
        $digits = preg_replace('/\D+/', '', (string) ($channels['whatsapp_e164'] ?? ''));
        if ($digits === '') {
            return null;
        }

        $room = $this->selectedRoom();
        $setting = Setting::first();
        $total = $booking?->total_amount ?? $this->estimatedTotal();
        $discount = '';
        if ($booking && (float) ($booking->discount_amount ?? 0) > 0) {
            $discount = 'Discount: '.($booking->discount_label ?: 'Offer').' — '.hotel_price($booking->discount_amount, $setting);
        }

        $text = site_trans('booking.wizard_whatsapp_message', [
            'names' => (string) $this->names,
            'room' => $room?->title ?? '',
            'checkin' => (string) $this->checkin,
            'checkout' => (string) $this->checkout,
            'adults' => (string) $this->adults,
            'children' => (string) $this->children,
            'country' => (string) $this->country,
            'party' => (string) $this->party_type,
            'ref' => $bookingId ? '#'.$bookingId : '',
            'email' => (string) $this->email,
            'phone' => (string) $this->phone,
            'total' => hotel_price($total, $setting),
            'discount' => $discount,
        ]);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    public function render()
    {
        $setting = Setting::first();
        $availability = $this->availability();
        $availableRooms = Room::query()
            ->where('status', 'Active')
            ->orderBy('title')
            ->get();

        $channels = HotelChannels::all();
        $publicPhone = hotel_public_phone($setting);
        $publicEmail = $channels['public_email'] ?? '';
        $hasWhatsapp = filled(preg_replace('/\D+/', '', (string) ($channels['whatsapp_e164'] ?? '')));
        $selectedRoom = $this->selectedRoom();
        $summary = $selectedRoom
            ? $availability->summary($selectedRoom, (string) $this->checkin, (string) $this->checkout)
            : null;

        $quotes = [];
        $availabilityByRoom = [];
        foreach ($availableRooms as $room) {
            $availabilityByRoom[$room->id] = $availability->summary($room, (string) $this->checkin, (string) $this->checkout);
            $quotes[$room->id] = app(RoomDiscountService::class)->quote(
                $room,
                (string) $this->checkin,
                (string) $this->checkout,
                (int) $this->adults,
                (int) $this->children,
                1,
                0,
                $this->board !== '' ? $this->board : null,
                $this->occupancy !== '' ? $this->occupancy : null
            );
        }
        $selectedQuote = $this->quoteFor($selectedRoom);
        $activeDiscounts = RoomDiscount::query()
            ->with('room:id,title')
            ->where('status', 'Active')
            ->when(filled($this->checkin) && filled($this->checkout), function ($query) {
                $query->whereDate('starts_on', '<=', $this->checkout)
                    ->whereDate('ends_on', '>=', $this->checkin);
            })
            ->orderBy('starts_on')
            ->get();

        $roomInventory = (int) ($summary['inventory'] ?? 1);
        $roomsAvailable = (int) ($summary['available'] ?? $roomInventory);
        $maxOccupancy = (int) ($summary['max_occupancy'] ?? 2);
        $maxGuests = $maxOccupancy * max(1, (int) $this->room_count);
        $minRoomsNeeded = $selectedRoom
            ? $availability->minRoomsForGuests($selectedRoom, (int) $this->adults, (int) $this->children)
            : 1;

        return view('frontend.book-now', [
            'setting' => $setting,
            'availableRooms' => $availableRooms,
            'selectedRoom' => $selectedRoom,
            'roomInventory' => $roomInventory,
            'roomsAvailable' => $roomsAvailable,
            'roomsHeld' => (int) ($summary['held'] ?? 0),
            'maxOccupancy' => $maxOccupancy,
            'maxGuests' => $maxGuests,
            'minRoomsNeeded' => $minRoomsNeeded,
            'datesReady' => (bool) ($summary['dates_ready'] ?? false),
            'stayReasons' => BookingStayReasons::options(),
            'nights' => $this->nights(),
            'estimatedTotal' => $selectedQuote['total'],
            'selectedQuote' => $selectedQuote,
            'quotes' => $quotes,
            'availabilityByRoom' => $availabilityByRoom,
            'activeDiscounts' => $activeDiscounts,
            'publicPhone' => $publicPhone,
            'publicEmail' => $publicEmail,
            'hasWhatsapp' => $hasWhatsapp,
        ]);
    }
}
