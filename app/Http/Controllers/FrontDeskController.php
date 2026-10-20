<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingPayment;
use App\Models\ExtraItem;
use App\Models\Room;
use App\Models\RoomClosure;
use App\Models\RoomDiscount;
use App\Models\Setting;
use App\Services\RoomDiscountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FrontDeskController extends Controller
{
    public function grant()
    {
        $this->assertReservationsManagerOwner();

        return redirect()->to(route('setting').'#reservations-manager');
    }

    public function updateGrant(Request $request)
    {
        $this->assertReservationsManagerOwner();

        $setting = Setting::query()->firstOrFail();
        $setting->reservations_manager_enabled = $request->boolean('reservations_manager_enabled');
        $setting->save();

        return redirect()
            ->to(route('setting').'#reservations-manager')
            ->with('success', $setting->reservations_manager_enabled
                ? 'Reservations Manager is active.'
                : 'Reservations Manager is off. Website management and guest reservations stay available.');
    }

    private function assertReservationsManagerOwner(): void
    {
        $email = strtolower((string) auth()->user()?->email);
        abort_unless($email === 'admin@iremetech.com', 403);
    }

    public function home()
    {
        $recent = $this->roomBookings()->with(['room', 'assignedRoom', 'creator', 'user'])->latest()->limit(8)->get();
        $today = now()->toDateString();
        $setting = Setting::query()->first();
        $stats = [
            'arriving' => $this->roomBookings()->whereDate('checkin_date', $today)->whereNull('checked_in_at')->count(),
            'in_house' => $this->roomBookings()->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
            'departing' => $this->roomBookings()->whereDate('checkout_date', $today)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
            'balance' => $this->roomBookings()
                ->where('balance_amount', '>', 0)
                ->get(['currency', 'balance_amount'])
                ->groupBy(fn (Booking $booking) => $booking->currencyKey())
                ->map(fn ($rows, $currency) => hotel_price($rows->sum('balance_amount'), $setting, $currency))
                ->implode(' · ') ?: hotel_price(0, $setting),
        ];

        return view('content-management.front-office.home', compact('recent', 'stats', 'setting'));
    }

    public function calendar(Request $request)
    {
        $start = Carbon::parse($request->input('start', now()->toDateString()))->startOfDay();
        $days = 14;
        $dates = collect(range(0, $days - 1))->map(fn ($i) => $start->copy()->addDays($i));
        $end = $dates->last()->copy()->addDay();
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $bookings = $this->overlapping($start, $end)->with(['room', 'assignedRoom'])->get();
        $closures = RoomClosure::query()
            ->whereDate('starts_on', '<', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get()
            ->groupBy('room_id');

        $rows = $rooms->map(function (Room $room) use ($bookings, $closures, $start, $days) {
            $stays = $bookings->filter(function (Booking $booking) use ($room) {
                $roomId = $booking->assigned_room_id ?: $booking->room_id;

                return (int) $roomId === (int) $room->id;
            })->values();

            return [
                'room' => $room,
                'bars' => $this->bars($stays, $closures->get($room->id, collect()), $start, $days),
            ];
        });

        $unallocated = $bookings->filter(fn (Booking $booking) => ! $booking->assigned_room_id && ! $booking->room_id)->values();

        return view('content-management.front-office.calendar', [
            'start' => $start,
            'dates' => $dates,
            'rows' => $rows,
            'unallocated' => $this->bars($unallocated, collect(), $start, $days),
            'prev' => $start->copy()->subDays($days)->toDateString(),
            'next' => $start->copy()->addDays($days)->toDateString(),
        ]);
    }

    public function reservations(Request $request)
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $reservations = $this->roomBookings()
            ->with(['room', 'assignedRoom', 'creator', 'user'])
            ->when($start, fn ($query) => $query->whereDate('checkout_date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('checkin_date', '<=', $end))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('content-management.front-office.reservations', compact('reservations', 'start', 'end'));
    }

    public function create(Request $request)
    {
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $booking = new Booking();
        $roomId = (int) $request->input('room');
        if ($rooms->contains(fn (Room $room) => (int) $room->id === $roomId)) {
            $booking->room_id = $roomId;
        }
        $checkin = $this->calendarDate($request->input('checkin'));
        if ($checkin) {
            $booking->checkin_date = $checkin;
            $checkout = $this->calendarDate($request->input('checkout'));
            $booking->checkout_date = ($checkout && $checkout->greaterThan($checkin))
                ? $checkout
                : $checkin->copy()->addDay();
        }
        if (! $booking->room_id) {
            $booking->room_id = $rooms->first()?->id;
        }
        if (! $booking->checkin_date) {
            $booking->checkin_date = now()->startOfDay();
            $booking->checkout_date = now()->addDay()->startOfDay();
        }
        $booking->reservation_channel = 'walkin';
        $booking->guest_source = 'unspecified';
        $booking->adults = 2;
        $booking->occupancy = 'double';
        $booking->currency = hotel_currency();

        return view('content-management.front-office.create', [
            'rooms' => $rooms,
            'roomRates' => $this->roomRateMap($rooms),
            'booking' => $booking,
            'setting' => Setting::query()->first(),
            'preview' => $booking->room_id ? $this->presentStay([
                'room_id' => $booking->room_id,
                'checkin_date' => $booking->checkin_date->toDateString(),
                'checkout_date' => $booking->checkout_date->toDateString(),
                'adults' => 2,
                'children' => 0,
                'board' => null,
                'occupancy' => 'double',
                'currency' => $booking->currency,
            ], 0) : null,
        ]);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'checkin_date' => 'required|date',
            'checkout_date' => 'required|date|after:checkin_date',
            'room_id' => 'required|exists:rooms,id',
            'board' => ['nullable', Rule::in(array_keys(Room::BOARD_PLANS))],
            'occupancy' => 'nullable|in:single,double',
            'currency' => 'nullable|in:usd,rwf',
            'adults' => 'required|integer|min:1|max:6',
            'children' => 'nullable|integer|min:0|max:4',
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);
        $paid = 0.0;
        if (! empty($data['booking_id'])) {
            $existing = Booking::query()->find($data['booking_id']);
            if ($existing) {
                $this->assertRoomBooking($existing);
                $paid = (float) $existing->paid_amount;
                $charges = (float) $existing->charges_amount;
            }
        }

        return response()->json($this->presentStay($data, $paid, $charges ?? 0));
    }

    public function store(Request $request)
    {
        $data = $this->validateStay($request);
        $booking = new Booking();
        $booking->status = 'pending';
        $booking->payment_status = 'pending';
        $booking->paid_amount = 0;
        $booking->reservation_type = 'room';
        $this->fillStay($booking, $data, true);
        $booking->created_by = auth()->id();
        $booking->save();

        return $this->redirectAfterStay($request, $booking, true);
    }

    public function show(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->load(['room', 'assignedRoom', 'payments.receiver', 'charges']);
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $selected = $rooms->firstWhere('id', $booking->assigned_room_id ?: $booking->room_id) ?? $booking->stayRoom();

        return view('content-management.front-office.show', [
            'booking' => $booking,
            'rooms' => $rooms,
            'selected' => $selected,
            'roomRates' => $this->roomRateMap($rooms),
            'plans' => $selected ? $selected->publicRatePlans() : [],
            'setting' => Setting::query()->first(),
            'items' => ExtraItem::query()->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $data = $this->validateStay($request, $booking);
        $this->guardCurrency($booking, $data);
        $changed = $this->rateChanged($booking, $data);
        $this->fillStay($booking, $data, $changed || (float) $booking->total_amount <= 0);
        $booking->save();

        return $this->redirectAfterStay($request, $booking, false);
    }

    public function confirmCancel(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        if ($booking->status === 'cancelled') {
            return redirect()
                ->route('content-management.front-office.reservations')
                ->with('error', 'This reservation is already cancelled.');
        }

        $booking->load(['room', 'assignedRoom']);

        return view('content-management.front-office.cancel', compact('booking'));
    }

    public function checkInForm(Booking $booking)
    {
        return $this->sessionForm($booking, 'checkin');
    }

    public function checkOutForm(Booking $booking)
    {
        return $this->sessionForm($booking, 'checkout');
    }

    public function checkIn(Request $request, Booking $booking)
    {
        return $this->saveSession($request, $booking, 'checkin');
    }

    public function checkOut(Request $request, Booking $booking)
    {
        return $this->saveSession($request, $booking, 'checkout');
    }

    public function storeCharge(Request $request, Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $data = $request->validate([
            'lines' => 'required|array|min:1|max:30',
            'lines.*.extra_item_id' => 'required|exists:extra_items,id',
            'lines.*.quantity' => 'required|integer|min:1|max:20',
            'lines.*.sale_price' => 'required|numeric|min:0',
            'lines.*.included' => 'nullable|boolean',
        ]);
        $count = 0;
        foreach ($data['lines'] as $line) {
            $item = ExtraItem::query()->where('status', 'Active')->find($line['extra_item_id']);
            if (! $item) {
                continue;
            }
            $included = filter_var($line['included'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $sale = round((float) $line['sale_price'], 2);
            $quantity = (int) $line['quantity'];
            $catalog = $booking->currencyKey() === 'rwf' ? $item->price_rwf : $item->price;
            $booking->charges()->create([
                'extra_item_id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'kind' => $item->kind,
                'quantity' => $quantity,
                'unit_price' => round((float) ($catalog ?? $sale), 2),
                'sale_price' => $sale,
                'included' => $included,
                'line_total' => $included ? 0 : round($sale * $quantity, 2),
                'created_by' => auth()->id(),
            ]);
            $count++;
        }
        if ($count === 0) {
            return back()->with('error', 'Choose an active item before adding it to the bill.');
        }
        $this->refreshBalance($booking);
        $booking->save();

        return back()->with('success', $count === 1 ? '1 item was added to the bill.' : $count.' items were added to the bill.');
    }

    public function complimentary(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $this->refreshBalance($booking);
        $due = (float) $booking->total_amount + (float) $booking->charges_amount - (float) $booking->paid_amount;
        if ($due <= 0) {
            return back()->with('error', 'This bill has nothing left to mark complimentary.');
        }
        $booking->waived_amount = round($due, 2);
        $this->refreshBalance($booking);
        $booking->save();

        return back()->with('success', 'The remaining balance was marked complimentary.');
    }

    public function destroyCharge(Booking $booking, BookingCharge $charge)
    {
        $this->assertRoomBooking($booking);
        abort_unless((int) $charge->booking_id === (int) $booking->id, 404);
        $charge->delete();
        $this->refreshBalance($booking);
        $booking->save();

        return back()->with('success', 'The charge was removed.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $request->validate([
            'confirm_cancel' => 'accepted',
        ], [
            'confirm_cancel.accepted' => 'Confirm the cancellation before it can be saved.',
        ]);

        if ($booking->status === 'cancelled') {
            return redirect()
                ->route('content-management.front-office.reservations')
                ->with('error', 'This reservation is already cancelled.');
        }

        $booking->status = 'cancelled';
        $booking->save();

        return redirect()
            ->route('content-management.front-office.reservations')
            ->with('success', $booking->names.' was cancelled.');
    }

    public function pay(Request $request, Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(array_keys(BookingPayment::METHODS))],
            'note' => 'nullable|string|max:255',
        ]);

        $booking->payments()->create([
            'amount' => $data['amount'],
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
            'received_by' => auth()->id(),
        ]);
        $booking->paid_amount = (float) $booking->payments()->sum('amount');
        $this->refreshBalance($booking);
        $booking->save();

        return back()->with('success', 'Payment recorded.');
    }

    public function receipt(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->load(['room', 'assignedRoom', 'payments.receiver', 'charges']);
        $setting = Setting::query()->first();

        return view('content-management.front-office.receipt', compact('booking', 'setting'));
    }

    public function payments()
    {
        $payments = BookingPayment::query()->with(['booking.room', 'receiver'])->latest()->paginate(25);

        return view('content-management.front-office.payments', compact('payments'));
    }

    public function inventory(Request $request)
    {
        $start = Carbon::parse($request->input('start', now()->toDateString()))->startOfDay();
        $days = 14;
        $dates = collect(range(0, $days - 1))->map(fn ($i) => $start->copy()->addDays($i));
        $end = $dates->last()->copy()->addDay();
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $bookings = $this->overlapping($start, $end)->get();
        $closures = RoomClosure::query()
            ->whereDate('starts_on', '<', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get();

        $blocks = $rooms->map(function (Room $room) use ($dates, $bookings, $closures) {
            $availability = $dates->map(function (Carbon $date) use ($room, $bookings, $closures) {
                $booked = $bookings->contains(function (Booking $booking) use ($room, $date) {
                    $roomId = $booking->assigned_room_id ?: $booking->room_id;
                    if ((int) $roomId !== (int) $room->id || ! $booking->checkin_date || ! $booking->checkout_date) {
                        return false;
                    }

                    return $date->gte($booking->checkin_date) && $date->lt($booking->checkout_date);
                });
                if ($booked) {
                    return 'booked';
                }

                $closed = $closures->contains(function (RoomClosure $closure) use ($room, $date) {
                    return (int) $closure->room_id === (int) $room->id
                        && $date->gte($closure->starts_on)
                        && $date->lte($closure->ends_on);
                });

                return $closed ? 'closed' : 'open';
            });

            return [
                'room' => $room,
                'availability' => $availability,
                'plans' => $room->ratePlans(),
            ];
        });

        return view('content-management.front-office.inventory', [
            'start' => $start,
            'dates' => $dates,
            'blocks' => $blocks,
            'setting' => Setting::query()->first(),
            'prev' => $start->copy()->subDays($days)->toDateString(),
            'next' => $start->copy()->addDays($days)->toDateString(),
        ]);
    }

    public function reports(Request $request)
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->toDateString());
        $bookings = $this->roomBookings()
            ->whereDate('checkin_date', '>=', $start)
            ->whereDate('checkin_date', '<=', $end)
            ->where('status', '!=', 'cancelled')
            ->get();

        $charges = BookingCharge::query()
            ->with('booking')
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->get();
        $setting = Setting::query()->first();
        $summaries = collect(['usd', 'rwf'])->map(function (string $currency) use ($bookings, $charges) {
            $rows = $bookings->filter(fn (Booking $booking) => $booking->currencyKey() === $currency);
            $posted = $charges->filter(fn (BookingCharge $charge) => ($charge->booking?->currencyKey() ?? 'usd') === $currency);

            return [
                'currency' => $currency,
                'total' => (float) $rows->sum('total_amount'),
                'restaurant' => (float) $posted->where('category', 'restaurant')->where('included', false)->sum('line_total'),
                'services' => (float) $posted->where('category', 'service')->where('included', false)->sum('line_total'),
                'paid' => (float) $rows->sum('paid_amount'),
                'balance' => (float) $rows->sum('balance_amount'),
                'visible' => $rows->isNotEmpty() || $posted->isNotEmpty(),
            ];
        })->filter(fn (array $row) => $row['visible'])->values();
        if ($summaries->isEmpty()) {
            $summaries = collect([[
                'currency' => 'usd',
                'total' => 0.0,
                'restaurant' => 0.0,
                'services' => 0.0,
                'paid' => 0.0,
                'balance' => 0.0,
            ]]);
        }

        return view('content-management.front-office.reports', [
            'bookings' => $bookings,
            'charges' => $charges,
            'start' => $start,
            'end' => $end,
            'summaries' => $summaries,
            'setting' => $setting,
            'channels' => $bookings->countBy(fn (Booking $booking) => $booking->channelKey())->all(),
            'sources' => $bookings->countBy(fn (Booking $booking) => $booking->guest_source ?: 'unspecified')->all(),
        ]);
    }

    public function extras()
    {
        $items = ExtraItem::query()->orderBy('category')->orderBy('name')->get();

        return view('content-management.front-office.settings.extras', compact('items'));
    }

    public function storeExtra(Request $request)
    {
        ExtraItem::query()->create($this->validatedExtra($request));

        return redirect()
            ->route('content-management.front-office.settings.extras.index')
            ->with('success', 'The item was added.');
    }

    public function updateExtra(Request $request, ExtraItem $item)
    {
        $item->update($this->validatedExtra($request));

        return redirect()
            ->route('content-management.front-office.settings.extras.index')
            ->with('success', $item->name.' was saved.');
    }

    public function destroyExtra(ExtraItem $item)
    {
        $name = $item->name;
        $item->delete();

        return redirect()
            ->route('content-management.front-office.settings.extras.index')
            ->with('success', $name.' was removed. Charges already on a bill keep their saved price.');
    }

    public function settings()
    {
        return redirect()->route('content-management.front-office.settings.rates.index');
    }

    public function rates()
    {
        $rooms = Room::query()->orderBy('title')->get();
        $closures = RoomClosure::query()->with('room')->orderByDesc('starts_on')->get();
        $discounts = RoomDiscount::query()->with('room')->orderByDesc('starts_on')->get();
        $setting = Setting::query()->first();

        return view('content-management.front-office.settings.rates', compact('rooms', 'closures', 'discounts', 'setting'));
    }

    public function invoiceSettings()
    {
        $setting = Setting::query()->first();

        return view('content-management.front-office.settings.invoice', compact('setting'));
    }

    public function updateInvoice(Request $request)
    {
        $data = $request->validate([
            'invoice_legal_name' => 'nullable|string|max:255',
            'invoice_address' => 'nullable|string|max:500',
            'invoice_phone' => 'nullable|string|max:40',
            'invoice_email' => 'nullable|email|max:255',
            'invoice_tax_id' => 'nullable|string|max:80',
            'invoice_footer' => 'nullable|string|max:1000',
        ]);

        $setting = Setting::query()->firstOrFail();
        foreach ($data as $field => $value) {
            $setting->{$field} = filled($value) ? $value : null;
        }
        $setting->save();

        return redirect()
            ->to(url('/content-management/front-office/settings/invoice'))
            ->with('success', 'Invoice details saved.');
    }

    public function updateRoomRates(Request $request, Room $room)
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'price_rwf' => 'nullable|numeric|min:0',
            'rates' => 'nullable|array',
            'rates.*.single' => 'nullable|numeric|min:0',
            'rates.*.double' => 'nullable|numeric|min:0',
            'rates.*.single_rwf' => 'nullable|numeric|min:0',
            'rates.*.double_rwf' => 'nullable|numeric|min:0',
            'rates.*.discount_type' => 'nullable|in:amount,percent',
            'rates.*.discount_value' => 'nullable|numeric|min:0',
            'rates.*.discount_value_rwf' => 'nullable|numeric|min:0',
        ]);

        $room->price = round((float) $data['price'], 2);
        $room->price_rwf = $request->filled('price_rwf') ? round((float) $data['price_rwf'], 2) : null;
        $room->rate_plans = $this->ratePlansFromRequest($request);
        $room->save();

        return redirect()
            ->to(url('/content-management/front-office/settings/rates'))
            ->with('success', $room->title.' rates saved.');
    }

    public function storeDiscount(Request $request)
    {
        RoomDiscount::query()->create($this->validatedDiscount($request));

        return redirect()
            ->to(url('/content-management/front-office/settings/rates'))
            ->with('success', 'Discount saved.');
    }

    public function updateDiscount(Request $request, RoomDiscount $discount)
    {
        $discount->update($this->validatedDiscount($request));

        return redirect()
            ->to(url('/content-management/front-office/settings/rates'))
            ->with('success', 'Discount updated.');
    }

    public function destroyDiscount(RoomDiscount $discount)
    {
        $discount->delete();

        return redirect()
            ->to(url('/content-management/front-office/settings/rates'))
            ->with('success', 'Discount removed.');
    }

    public function storeClosure(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'note' => 'nullable|string|max:255',
        ]);
        RoomClosure::query()->create($data);

        return back()->with('success', 'Room closure saved.');
    }

    public function destroyClosure(RoomClosure $closure)
    {
        $closure->delete();

        return back()->with('success', 'Room closure removed.');
    }

    private function roomBookings()
    {
        return Booking::query()->where(function ($query) {
            $query->where('reservation_type', 'room')->orWhereNull('reservation_type');
        });
    }

    private function sessionForm(Booking $booking, string $mode)
    {
        $this->assertRoomBooking($booking);
        if ($mode === 'checkin' && $booking->checked_out_at) {
            return redirect()
                ->route('content-management.front-office.show', $booking)
                ->with('error', 'This guest has already checked out.');
        }
        if ($mode === 'checkout' && ! $booking->checked_in_at) {
            return redirect()
                ->route('content-management.front-office.check-in.form', $booking)
                ->with('error', 'Check the guest in before check-out.');
        }
        if ($mode === 'checkin' && $booking->checked_in_at) {
            return redirect()
                ->route('content-management.front-office.show', $booking)
                ->with('success', 'This guest is already checked in.');
        }

        $booked = $mode === 'checkin'
            ? ($booking->booked_checkin_date ?: $booking->checkin_date)
            : ($booking->booked_checkout_date ?: $booking->checkout_date);
        $suggested = $booked ? Carbon::parse($booked)->startOfDay() : now()->startOfDay();
        if ($mode === 'checkout' && $suggested->gt(now()->startOfDay())) {
            $suggested = now()->startOfDay();
        }

        $booking->load(['room', 'assignedRoom', 'charges', 'payments.receiver']);

        return view('content-management.front-office.session', [
            'booking' => $booking,
            'mode' => $mode,
            'suggested' => $suggested,
            'setting' => Setting::query()->first(),
            'items' => ExtraItem::query()->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    private function saveSession(Request $request, Booking $booking, string $mode)
    {
        $this->assertRoomBooking($booking);
        $data = $request->validate([
            'stay_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'first_name' => 'required|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'adults' => 'required|integer|min:1|max:6',
            'children' => 'nullable|integer|min:0|max:4',
            'comment' => 'nullable|string|max:1000',
            'intent' => 'required|in:revise,confirm',
        ]);
        $confirm = $data['intent'] === 'confirm';
        if ($confirm && ! $request->boolean('confirm_session')) {
            return back()->withInput()->with('error', $mode === 'checkin'
                ? 'Confirm that the guest is here before check-in is saved.'
                : 'Confirm that the guest has left before check-out is saved.');
        }

        $stayDate = Carbon::parse($data['stay_date'])->startOfDay();
        $today = now()->startOfDay();
        $booked = $mode === 'checkin'
            ? Carbon::parse($booking->booked_checkin_date ?: $booking->checkin_date)->startOfDay()
            : Carbon::parse($booking->booked_checkout_date ?: $booking->checkout_date)->startOfDay();
        $changed = $stayDate->toDateString() !== $booked->toDateString();
        if ($changed && strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
            return back()->withInput()->with('error', 'Say why this date is different from the booked date.');
        }
        if ($confirm && $stayDate->gt($today)) {
            return back()->withInput()->with('error', $mode === 'checkin'
                ? 'Check-in is for a guest who is here. Save the date change if the guest is still coming later.'
                : 'Set the checkout date to today or earlier. A later date keeps the suite booked.');
        }

        $checkin = $mode === 'checkin' ? $stayDate->copy() : Carbon::parse($booking->checkin_date)->startOfDay();
        $checkout = $mode === 'checkout' ? $stayDate->copy() : Carbon::parse($booking->checkout_date)->startOfDay();
        if (! $checkout->gt($checkin)) {
            return back()->withInput()->with('error', 'Check-out has to be after check-in.');
        }
        $conflict = $this->stayConflict($booking, $checkin->toDateString(), $checkout->toDateString());
        if ($conflict) {
            return back()->withInput()->with('error', $conflict);
        }

        if (! $booking->booked_checkin_date) {
            $booking->booked_checkin_date = $booking->checkin_date;
            $booking->booked_checkout_date = $booking->checkout_date;
        }
        $booking->names = trim($data['first_name'].' '.($data['last_name'] ?? ''));
        $booking->email = $data['email'] ?? null;
        $booking->phone = $data['phone'] ?? null;
        $booking->adults = $data['adults'];
        $booking->children = $data['children'] ?? 0;
        $booking->message = $data['comment'] ?? $booking->message;
        $booking->checkin_date = $checkin->toDateString();
        $booking->checkout_date = $checkout->toDateString();
        $booking->checkin = $booking->checkin_date;
        $booking->checkout = $booking->checkout_date;
        if ($mode === 'checkin') {
            $booking->checkin_note = $changed ? trim((string) $data['reason']) : $booking->checkin_note;
        } else {
            $booking->checkout_note = $changed ? trim((string) $data['reason']) : $booking->checkout_note;
        }
        $this->repriceStay($booking);

        if ($confirm && $mode === 'checkin') {
            $roomId = $booking->assigned_room_id ?: $booking->room_id;
            $booking->assigned_room_id = $roomId;
            $booking->room_id = $booking->room_id ?: $roomId;
            $booking->status = 'confirmed';
            $booking->checked_in_at = $booking->checked_in_at ?: now();
            $booking->checked_in_by = $booking->checked_in_by ?: auth()->id();
        }
        if ($confirm && $mode === 'checkout') {
            $booking->checked_out_at = now();
            $booking->checked_out_by = auth()->id();
        }
        $booking->save();

        if (! $confirm) {
            return back()->with('success', 'The date was updated. The guest was not '.($mode === 'checkin' ? 'checked in.' : 'checked out.'));
        }

        return redirect()
            ->route('content-management.front-office.reservations')
            ->with('success', $mode === 'checkin' ? 'Guest checked in.' : 'Guest checked out.');
    }

    private function repriceStay(Booking $booking): void
    {
        $room = Room::query()->find($booking->room_id);
        if (! $room || ! $booking->checkin_date || ! $booking->checkout_date) {
            $this->refreshBalance($booking);

            return;
        }
        $quote = app(RoomDiscountService::class)->quote(
            $room,
            Carbon::parse($booking->checkin_date)->toDateString(),
            Carbon::parse($booking->checkout_date)->toDateString(),
            (int) $booking->adults,
            (int) ($booking->children ?? 0),
            1,
            0,
            $booking->board,
            $booking->occupancy,
            $booking->currencyKey()
        );
        $booking->total_amount = round($quote['total'], 2);
        $booking->list_amount = round($quote['list_total'], 2);
        $booking->discount_amount = round($quote['discount_total'], 2);
        $booking->discount_label = $quote['label'] !== '' ? $quote['label'] : null;
        $this->refreshBalance($booking);
    }

    private function refreshBalance(Booking $booking): void
    {
        $charges = $booking->exists
            ? (float) $booking->charges()->where('included', false)->sum('line_total')
            : (float) ($booking->charges_amount ?? 0);
        $booking->charges_amount = round($charges, 2);
        $due = max(0, round((float) $booking->total_amount + $booking->charges_amount - (float) $booking->paid_amount, 2));
        $waived = min((float) ($booking->waived_amount ?? 0), $due);
        $booking->waived_amount = round($waived, 2);
        $booking->balance_amount = round($due - $waived, 2);
        if ($booking->balance_amount <= 0 && ((float) $booking->paid_amount > 0 || $waived > 0)) {
            $booking->payment_status = 'paid';
        } elseif ((float) $booking->paid_amount > 0 || $waived > 0) {
            $booking->payment_status = 'partial';
        } else {
            $booking->payment_status = 'pending';
        }
    }

    private function stayConflict(Booking $booking, string $checkin, string $checkout): ?string
    {
        $roomId = (int) ($booking->assigned_room_id ?: $booking->room_id);
        if (! $roomId) {
            return 'Choose a suite before changing these dates.';
        }
        $overlap = $this->overlapping(Carbon::parse($checkin), Carbon::parse($checkout))
            ->where('id', '!=', $booking->id)
            ->get()
            ->first(function (Booking $other) use ($roomId) {
                return (int) ($other->assigned_room_id ?: $other->room_id) === $roomId;
            });
        if ($overlap) {
            return $overlap->names.' already has this suite for those nights.';
        }
        $closed = RoomClosure::query()
            ->where('room_id', $roomId)
            ->whereDate('starts_on', '<', $checkout)
            ->whereDate('ends_on', '>=', $checkin)
            ->exists();

        return $closed ? 'The suite is closed for one of these nights.' : null;
    }

    private function validatedExtra(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'kind' => ['required', Rule::in(array_keys(ExtraItem::KINDS))],
            'price' => 'required|numeric|min:0',
            'price_rwf' => 'nullable|numeric|min:0',
            'status' => 'required|in:Active,Inactive',
        ]);
        $data['category'] = ExtraItem::KINDS[$data['kind']]['category'];
        $data['price'] = round((float) $data['price'], 2);
        $data['price_rwf'] = $request->filled('price_rwf') ? round((float) $data['price_rwf'], 2) : null;

        return $data;
    }

    private function overlapping(Carbon $start, Carbon $end)
    {
        return $this->roomBookings()
            ->whereNotIn('status', ['cancelled', 'No Show'])
            ->whereDate('checkin_date', '<', $end->toDateString())
            ->whereDate('checkout_date', '>', $start->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDiscount(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'room_id' => 'nullable|exists:rooms,id',
            'discount_type' => 'required|in:percent,amount',
            'amount' => 'required|numeric|min:0.01',
            'amount_rwf' => 'nullable|numeric|min:0',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($data['discount_type'] === 'percent' && (float) $data['amount'] > 100) {
            $data['amount'] = 100;
        }
        $data['amount_rwf'] = $data['discount_type'] === 'amount' && $request->filled('amount_rwf')
            ? round((float) $data['amount_rwf'], 2)
            : null;

        $data['room_id'] = filled($data['room_id'] ?? null) ? (int) $data['room_id'] : null;

        return $data;
    }

    /**
     * @return array<string, array{single: ?float, double: ?float, discount_type: string, discount_value: ?float}>|null
     */
    private function ratePlansFromRequest(Request $request): ?array
    {
        $incoming = $request->input('rates', []);
        $plans = [];
        $any = false;

        foreach (array_keys(Room::BOARD_PLANS) as $key) {
            $row = is_array($incoming[$key] ?? null) ? $incoming[$key] : [];
            $single = $this->rateMoney($row['single'] ?? null);
            $double = $this->rateMoney($row['double'] ?? null);
            $singleRwf = $this->rateMoney($row['single_rwf'] ?? null);
            $doubleRwf = $this->rateMoney($row['double_rwf'] ?? null);
            $type = in_array($row['discount_type'] ?? '', ['amount', 'percent'], true) ? $row['discount_type'] : '';
            $value = $this->rateMoney($row['discount_value'] ?? null);
            $valueRwf = $this->rateMoney($row['discount_value_rwf'] ?? null);
            if ($type === 'percent' && $value !== null) {
                $value = min(100, $value);
            }
            $priced = $single !== null || $double !== null || $singleRwf !== null || $doubleRwf !== null;
            if (! $priced || $type === '' || ($type === 'percent' && ($value === null || $value <= 0)) || ($type === 'amount' && ($value === null || $value <= 0) && ($valueRwf === null || $valueRwf <= 0))) {
                $type = '';
                $value = null;
                $valueRwf = null;
            }
            if ($type === 'percent') {
                $valueRwf = null;
            }
            if ($priced) {
                $any = true;
            }
            $plans[$key] = [
                'single' => $single,
                'double' => $double,
                'single_rwf' => $singleRwf,
                'double_rwf' => $doubleRwf,
                'discount_type' => $type,
                'discount_value' => $value,
                'discount_value_rwf' => $type === 'amount' ? $valueRwf : null,
            ];
        }

        return $any ? $plans : null;
    }

    private function rateMoney(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return $number < 0 ? null : $number;
    }

    private function assertRoomBooking(Booking $booking): void
    {
        abort_unless($booking->reservation_type === 'room' || $booking->reservation_type === null, 404);
    }

    private function validateStay(Request $request, ?Booking $booking = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:30',
            'checkin_date' => 'required|date',
            'checkout_date' => 'required|date|after:checkin_date',
            'room_id' => 'required|exists:rooms,id',
            'board' => ['nullable', Rule::in(array_keys(Room::BOARD_PLANS))],
            'occupancy' => 'nullable|in:single,double',
            'adults' => 'required|integer|min:1|max:6',
            'children' => 'nullable|integer|min:0|max:4',
            'reservation_channel' => ['required', Rule::in(array_keys(Booking::CHANNELS))],
            'guest_source' => ['required', Rule::in(array_keys(Booking::SOURCES))],
            'currency' => 'required|in:usd,rwf',
            'message' => 'nullable|string|max:1000',
        ]);
    }

    private function redirectAfterStay(Request $request, Booking $booking, bool $created)
    {
        $after = (string) $request->input('after');
        if ($after === 'checkin' && ! $booking->checked_out_at) {
            return redirect()
                ->route('content-management.front-office.check-in.form', $booking)
                ->with('success', 'Reservation saved. Review the arrival before checking the guest in.');
        }

        if ($after === 'invoice') {
            return redirect()
                ->route('content-management.front-office.receipt', ['booking' => $booking, 'print' => 1])
                ->with('success', 'Reservation saved.');
        }

        if ($after === 'payment') {
            return redirect()
                ->to(route('content-management.front-office.show', $booking).'#payment')
                ->with('success', $booking->names.' was saved. Record the payment below.');
        }

        if ($created) {
            return redirect()
                ->route('content-management.front-office.show', $booking)
                ->with('success', 'Reservation saved.');
        }

        return redirect()
            ->route('content-management.front-office.reservations')
            ->with('success', $booking->names.' was saved.');
    }

    private function presentStay(array $data, float $paid, float $charges = 0): array
    {
        $room = Room::query()->findOrFail($data['room_id']);
        $currency = ($data['currency'] ?? hotel_currency()) === 'rwf' ? 'rwf' : 'usd';
        $quote = app(RoomDiscountService::class)->quote(
            $room,
            $data['checkin_date'],
            $data['checkout_date'],
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            1,
            0,
            $data['board'] ?? null,
            $data['occupancy'] ?? null,
            $currency
        );
        $setting = Setting::query()->first();
        $total = round((float) $quote['total'], 2);
        $discount = round((float) $quote['discount_total'], 2);
        $paid = round($paid, 2);
        $charges = round($charges, 2);
        $grand = round($total + $charges, 2);
        $nightly = (float) $quote['nightly'];
        $rack = (float) ($quote['rack_nightly'] ?? $nightly);
        $guest = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        $nights = (int) $quote['nights'];

        return [
            'guest' => $guest !== '' ? $guest : 'Guest',
            'room' => $room->title,
            'stay' => Carbon::parse($data['checkin_date'])->format('d M Y').' – '.Carbon::parse($data['checkout_date'])->format('d M Y'),
            'nights' => $nights.' '.($nights === 1 ? 'night' : 'nights'),
            'board' => (Room::BOARD_PLANS[$data['board'] ?? ''] ?? 'Standard rate').' · '.(($data['occupancy'] ?? 'double') === 'single' ? 'Single' : 'Double'),
            'nightly' => hotel_price($nightly, $setting, $currency),
            'rack' => $rack > $nightly + 0.5 ? hotel_price($rack, $setting, $currency) : '',
            'discount' => hotel_price($discount, $setting, $currency),
            'discount_label' => $quote['label'] !== '' ? $quote['label'] : 'Discount',
            'has_discount' => $discount >= 0.5,
            'extras' => hotel_price($charges, $setting, $currency),
            'has_extras' => $charges >= 0.5,
            'total' => hotel_price($grand, $setting, $currency),
            'paid' => hotel_price($paid, $setting, $currency),
            'balance' => hotel_price(max(0, round($grand - $paid, 2)), $setting, $currency),
        ];
    }

    private function fillStay(Booking $booking, array $data, bool $reprice): void
    {
        $booking->names = trim($data['first_name'].' '.($data['last_name'] ?? ''));
        $booking->email = $data['email'] ?? null;
        $booking->phone = $data['phone'] ?? null;
        $booking->country = $data['country'] ?? null;
        $booking->address = $data['address'] ?? null;
        $booking->postal_code = $data['postal_code'] ?? null;
        $booking->checkin_date = $data['checkin_date'];
        $booking->checkout_date = $data['checkout_date'];
        $booking->checkin = $data['checkin_date'];
        $booking->checkout = $data['checkout_date'];
        if (! $booking->booked_checkin_date) {
            $booking->booked_checkin_date = $data['checkin_date'];
            $booking->booked_checkout_date = $data['checkout_date'];
        }
        $booking->room_id = $data['room_id'];
        $booking->adults = $data['adults'];
        $booking->children = $data['children'] ?? 0;
        $booking->rooms = 1;
        $booking->reservation_channel = $data['reservation_channel'];
        $booking->guest_source = $data['guest_source'];
        $booking->booking_type = $data['reservation_channel'] === 'website' ? 'online' : 'walkin';
        $booking->board = $data['board'] ?: null;
        $booking->occupancy = $data['occupancy'] ?: null;
        $booking->currency = ($data['currency'] ?? 'usd') === 'rwf' ? 'rwf' : 'usd';
        $booking->message = $data['message'] ?? $booking->message;

        if (! $reprice) {
            $this->refreshBalance($booking);

            return;
        }

        $room = Room::query()->findOrFail($data['room_id']);
        $quote = app(RoomDiscountService::class)->quote(
            $room,
            $data['checkin_date'],
            $data['checkout_date'],
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            1,
            0,
            $data['board'] ?? null,
            $data['occupancy'] ?? null,
            $booking->currency
        );
        $booking->board = $quote['board'] ?: $booking->board;
        $booking->occupancy = $quote['occupancy'] ?: $booking->occupancy;
        $booking->total_amount = round($quote['total'], 2);
        $booking->list_amount = round($quote['list_total'], 2);
        $booking->discount_amount = round($quote['discount_total'], 2);
        $booking->discount_label = $quote['label'] !== '' ? $quote['label'] : null;
        $this->refreshBalance($booking);
    }

    private function guardCurrency(Booking $booking, array $data): void
    {
        $next = ($data['currency'] ?? 'usd') === 'rwf' ? 'rwf' : 'usd';
        if ($next === $booking->currencyKey()) {
            return;
        }
        $locked = (float) $booking->paid_amount > 0
            || (float) ($booking->charges_amount ?? 0) > 0
            || (float) ($booking->waived_amount ?? 0) > 0
            || $booking->charges()->exists();
        if ($locked) {
            throw ValidationException::withMessages([
                'currency' => 'This stay already has a payment or an extra charge, so the invoice currency stays as it is.',
            ]);
        }
    }

    private function rateChanged(Booking $booking, array $data): bool
    {
        return (string) $booking->board !== (string) ($data['board'] ?? '')
            || (string) $booking->occupancy !== (string) ($data['occupancy'] ?? '')
            || (int) $booking->room_id !== (int) $data['room_id']
            || optional($booking->checkin_date)->toDateString() !== $data['checkin_date']
            || optional($booking->checkout_date)->toDateString() !== $data['checkout_date']
            || (int) $booking->adults !== (int) $data['adults']
            || (int) $booking->children !== (int) ($data['children'] ?? 0)
            || (int) ($booking->rooms ?? 1) !== 1
            || $booking->currencyKey() !== (($data['currency'] ?? 'usd') === 'rwf' ? 'rwf' : 'usd');
    }

    private function roomRateMap($rooms): array
    {
        return $rooms->mapWithKeys(function (Room $room) {
            return [$room->id => [
                'price' => (float) $room->price,
                'price_rwf' => $room->price_rwf,
                'plans' => collect($room->ratePlans())->map(fn ($plan, $key) => [
                    'key' => $key,
                    'label' => Room::BOARD_PLANS[$key] ?? $key,
                    'single' => $plan['single_sale'],
                    'double' => $plan['double_sale'],
                    'single_rwf' => $plan['single_sale_rwf'],
                    'double_rwf' => $plan['double_sale_rwf'],
                ])->filter(fn ($plan) => $plan['single'] !== null || $plan['double'] !== null || $plan['single_rwf'] !== null || $plan['double_rwf'] !== null)->values(),
            ]];
        })->all();
    }

    private function calendarDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function bars($stays, $closures, Carbon $start, int $days): array
    {
        $bars = [];
        foreach ($stays as $booking) {
            if (! $booking->checkin_date || ! $booking->checkout_date) {
                continue;
            }
            $from = Carbon::parse($booking->checkin_date)->startOfDay();
            $to = Carbon::parse($booking->checkout_date)->startOfDay();
            $windowEnd = $start->copy()->addDays($days);
            $barStart = $from->greaterThan($start) ? $from : $start->copy();
            $barEnd = $to->lessThan($windowEnd) ? $to : $windowEnd;
            $span = $barStart->diffInDays($barEnd, true);
            if ($span < 1) {
                continue;
            }
            $bars[] = [
                'id' => $booking->id,
                'label' => $booking->names,
                'state' => $booking->deskState(),
                'column' => (int) $start->diffInDays($barStart) + 1,
                'span' => (int) $span,
                'href' => route('content-management.front-office.show', $booking),
            ];
        }

        foreach ($closures as $closure) {
            $from = Carbon::parse($closure->starts_on)->startOfDay();
            $to = Carbon::parse($closure->ends_on)->startOfDay()->addDay();
            $windowEnd = $start->copy()->addDays($days);
            $barStart = $from->greaterThan($start) ? $from : $start->copy();
            $barEnd = $to->lessThan($windowEnd) ? $to : $windowEnd;
            $span = $barStart->diffInDays($barEnd, true);
            if ($span < 1) {
                continue;
            }
            $bars[] = [
                'id' => 'closure-'.$closure->id,
                'label' => $closure->note ?: 'Room closure',
                'state' => 'closure',
                'column' => (int) $start->diffInDays($barStart) + 1,
                'span' => (int) $span,
                'href' => null,
            ];
        }

        return $this->laneBars($bars);
    }

    private function laneBars(array $bars): array
    {
        $lanes = [];
        foreach ($bars as &$bar) {
            $lane = 0;
            while (collect($lanes[$lane] ?? [])->contains(function ($used) use ($bar) {
                $start = $bar['column'];
                $end = $bar['column'] + $bar['span'];

                return $start < ($used['column'] + $used['span']) && $end > $used['column'];
            })) {
                $lane++;
            }
            $lanes[$lane][] = $bar;
            $bar['lane'] = $lane;
        }

        return $bars;
    }
}
