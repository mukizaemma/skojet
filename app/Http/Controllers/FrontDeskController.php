<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Room;
use App\Models\RoomClosure;
use App\Models\Setting;
use App\Services\RoomDiscountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FrontDeskController extends Controller
{
    public function grant()
    {
        $enabled = reservations_manager_enabled();

        return view('content-management.reservations-manager.index', compact('enabled'));
    }

    public function updateGrant(Request $request)
    {
        $setting = Setting::query()->firstOrFail();
        $setting->reservations_manager_enabled = $request->boolean('reservations_manager_enabled');
        $setting->save();

        return redirect()
            ->route('content-management.reservations-manager')
            ->with('success', $setting->reservations_manager_enabled
                ? 'Reservations Manager is active.'
                : 'Reservations Manager is off. Website management and guest reservations stay available.');
    }

    public function home()
    {
        $recent = $this->roomBookings()->with(['room', 'assignedRoom'])->latest()->limit(8)->get();
        $today = now()->toDateString();
        $stats = [
            'arriving' => $this->roomBookings()->whereDate('checkin_date', $today)->whereNull('checked_in_at')->count(),
            'in_house' => $this->roomBookings()->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
            'departing' => $this->roomBookings()->whereDate('checkout_date', $today)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
            'balance' => (float) $this->roomBookings()->where('balance_amount', '>', 0)->sum('balance_amount'),
        ];

        return view('content-management.front-office.home', compact('recent', 'stats'));
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
            ->with(['room', 'assignedRoom'])
            ->when($start, fn ($query) => $query->whereDate('checkout_date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('checkin_date', '<=', $end))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('content-management.front-office.reservations', compact('reservations', 'start', 'end'));
    }

    public function create()
    {
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();

        return view('content-management.front-office.create', [
            'rooms' => $rooms,
            'roomRates' => $this->roomRateMap($rooms),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateStay($request);
        $booking = new Booking();
        $booking->booking_type = 'walkin';
        $booking->status = $request->boolean('check_in_now') ? 'confirmed' : 'pending';
        $booking->payment_status = 'pending';
        $booking->paid_amount = 0;
        $booking->reservation_type = 'room';
        $this->fillStay($booking, $data, true);
        if ($request->boolean('check_in_now')) {
            $booking->assigned_room_id = $data['room_id'];
            $booking->checked_in_at = now();
            $booking->checked_in_by = auth()->id();
        }
        $booking->save();

        return redirect()
            ->route('content-management.front-office.show', $booking)
            ->with('success', 'Reservation saved.');
    }

    public function show(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->load(['room', 'assignedRoom', 'payments.receiver']);
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $selected = $rooms->firstWhere('id', $booking->assigned_room_id ?: $booking->room_id) ?? $booking->stayRoom();

        return view('content-management.front-office.show', [
            'booking' => $booking,
            'rooms' => $rooms,
            'selected' => $selected,
            'roomRates' => $this->roomRateMap($rooms),
            'plans' => $selected ? $selected->publicRatePlans() : [],
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $data = $this->validateStay($request, $booking);
        $changed = $this->rateChanged($booking, $data);
        $this->fillStay($booking, $data, $changed || (float) $booking->total_amount <= 0);
        if ($request->input('after') === 'checkin') {
            $booking->assigned_room_id = $data['room_id'];
            $booking->status = 'confirmed';
            $booking->checked_in_at = $booking->checked_in_at ?: now();
            $booking->checked_in_by = $booking->checked_in_by ?: auth()->id();
        }
        $booking->save();

        if ($request->input('after') === 'checkin') {
            return redirect()
                ->route('content-management.front-office.show', $booking)
                ->with('success', 'Guest checked in.');
        }

        return redirect()
            ->route('content-management.front-office.show', $booking)
            ->with('success', 'Reservation updated.');
    }

    public function checkIn(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $roomId = $booking->assigned_room_id ?: $booking->room_id;
        if (! $roomId) {
            return back()->with('error', 'Choose a suite before check-in.');
        }
        if ($booking->checked_out_at) {
            return back()->with('error', 'This guest has already checked out.');
        }

        $booking->assigned_room_id = $roomId;
        $booking->room_id = $booking->room_id ?: $roomId;
        $booking->status = 'confirmed';
        $booking->checked_in_at = $booking->checked_in_at ?: now();
        $booking->checked_in_by = $booking->checked_in_by ?: auth()->id();
        $booking->save();

        return back()->with('success', 'Guest checked in.');
    }

    public function checkOut(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        if (! $booking->checked_in_at) {
            return back()->with('error', 'Check the guest in before check-out.');
        }

        $booking->checked_out_at = now();
        $booking->checked_out_by = auth()->id();
        $booking->save();

        return back()->with('success', 'Guest checked out.');
    }

    public function cancel(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->status = 'cancelled';
        $booking->save();

        return back()->with('success', 'Reservation cancelled.');
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
        $paid = (float) $booking->payments()->sum('amount');
        $booking->paid_amount = $paid;
        $booking->balance_amount = max(0, (float) $booking->total_amount - $paid);
        $booking->payment_status = $booking->balance_amount <= 0 ? 'paid' : 'partial';
        $booking->save();

        return back()->with('success', 'Payment recorded.');
    }

    public function receipt(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->load(['room', 'assignedRoom', 'payments.receiver']);
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
                $taken = $bookings->contains(function (Booking $booking) use ($room, $date) {
                    $roomId = $booking->assigned_room_id ?: $booking->room_id;
                    if ((int) $roomId !== (int) $room->id || ! $booking->checkin_date || ! $booking->checkout_date) {
                        return false;
                    }

                    return $date->gte($booking->checkin_date) && $date->lt($booking->checkout_date);
                });
                $closed = $closures->contains(function (RoomClosure $closure) use ($room, $date) {
                    return (int) $closure->room_id === (int) $room->id
                        && $date->gte($closure->starts_on)
                        && $date->lte($closure->ends_on);
                });

                return $taken || $closed ? 0 : 1;
            });

            return [
                'room' => $room,
                'availability' => $availability,
                'plans' => $room->publicRatePlans(),
            ];
        });

        return view('content-management.front-office.inventory', [
            'start' => $start,
            'dates' => $dates,
            'blocks' => $blocks,
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

        return view('content-management.front-office.reports', [
            'bookings' => $bookings,
            'start' => $start,
            'end' => $end,
            'total' => (float) $bookings->sum('total_amount'),
            'paid' => (float) $bookings->sum('paid_amount'),
            'balance' => (float) $bookings->sum('balance_amount'),
        ]);
    }

    public function settings()
    {
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();
        $closures = RoomClosure::query()->with('room')->orderByDesc('starts_on')->get();

        return view('content-management.front-office.settings', compact('rooms', 'closures'));
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

    private function overlapping(Carbon $start, Carbon $end)
    {
        return $this->roomBookings()
            ->whereNotIn('status', ['cancelled', 'No Show'])
            ->whereDate('checkin_date', '<', $end->toDateString())
            ->whereDate('checkout_date', '>', $start->toDateString());
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
            'rooms' => 'nullable|integer|min:1|max:4',
            'message' => 'nullable|string|max:1000',
        ]);
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
        $booking->room_id = $data['room_id'];
        $booking->adults = $data['adults'];
        $booking->children = $data['children'] ?? 0;
        $booking->rooms = $data['rooms'] ?? 1;
        $booking->board = $data['board'] ?: null;
        $booking->occupancy = $data['occupancy'] ?: null;
        $booking->message = $data['message'] ?? $booking->message;

        if (! $reprice) {
            $booking->balance_amount = max(0, (float) $booking->total_amount - (float) $booking->paid_amount);

            return;
        }

        $room = Room::query()->findOrFail($data['room_id']);
        $quote = app(RoomDiscountService::class)->quote(
            $room,
            $data['checkin_date'],
            $data['checkout_date'],
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            max(1, (int) ($data['rooms'] ?? 1)),
            0,
            $data['board'] ?? null,
            $data['occupancy'] ?? null
        );
        $booking->board = $quote['board'] ?: $booking->board;
        $booking->occupancy = $quote['occupancy'] ?: $booking->occupancy;
        $booking->total_amount = round($quote['total'], 2);
        $booking->list_amount = round($quote['list_total'], 2);
        $booking->discount_amount = round($quote['discount_total'], 2);
        $booking->discount_label = $quote['label'] !== '' ? $quote['label'] : null;
        $booking->balance_amount = max(0, (float) $booking->total_amount - (float) $booking->paid_amount);
        $booking->payment_status = $booking->balance_amount <= 0 && (float) $booking->paid_amount > 0
            ? 'paid'
            : ((float) $booking->paid_amount > 0 ? 'partial' : 'pending');
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
            || (int) ($booking->rooms ?? 1) !== (int) ($data['rooms'] ?? 1);
    }

    private function roomRateMap($rooms): array
    {
        return $rooms->mapWithKeys(function (Room $room) {
            return [$room->id => [
                'price' => (float) $room->price,
                'plans' => collect($room->publicRatePlans())->map(fn ($plan) => [
                    'key' => $plan['key'],
                    'label' => Room::BOARD_PLANS[$plan['key']] ?? $plan['key'],
                    'single' => $plan['single_sale'],
                    'double' => $plan['double_sale'],
                ])->values(),
            ]];
        })->all();
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
