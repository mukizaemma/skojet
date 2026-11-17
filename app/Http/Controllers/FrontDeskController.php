<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingPayment;
use App\Models\ExtraItem;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantReservation;
use App\Models\RestaurantReservationItem;
use App\Models\Room;
use App\Models\RoomClosure;
use App\Models\RoomOverbook;
use App\Models\RoomDiscount;
use App\Models\Setting;
use App\Services\RoomDiscountService;
use App\Support\PdfFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        return view('content-management.front-office.home', [
            'recent' => $recent,
            'stats' => $stats,
            'setting' => $setting,
            'restaurantReservations' => Schema::hasTable('restaurant_reservations'),
        ]);
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
        $overbooks = Schema::hasTable('room_overbooks')
            ? RoomOverbook::query()
                ->whereDate('starts_on', '<=', $dates->last()->toDateString())
                ->whereDate('ends_on', '>=', $start->toDateString())
                ->get()
                ->groupBy('room_id')
            : collect();

        $rows = $rooms->map(function (Room $room) use ($bookings, $closures, $overbooks, $start, $days) {
            $stays = $bookings->filter(function (Booking $booking) use ($room) {
                $roomId = $booking->assigned_room_id ?: $booking->room_id;

                return (int) $roomId === (int) $room->id;
            })->values();

            return [
                'room' => $room,
                'bars' => $this->bars($stays, $closures->get($room->id, collect()), $start, $days),
                'overbooks' => $overbooks->get($room->id, collect()),
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
        $filters = $this->stayFilters($request);
        $reservations = $this->stayQuery($filters)
            ->paginate(20)
            ->withQueryString();

        return view('content-management.front-office.reservations', compact('reservations', 'filters'));
    }

    public function exportReservationsPdf(Request $request)
    {
        $filters = $this->stayFilters($request);
        $reservations = $this->stayQuery($filters)->limit(2000)->get();

        return PdfFile::download(
            view('bills.pdf', [
                'title' => 'Room reservations',
                'body' => view('content-management.front-office.reservations-export', [
                    'reservations' => $reservations,
                    'filters' => $filters,
                    'setting' => Setting::query()->first(),
                ])->render(),
            ])->render(),
            'room-reservations.pdf',
            'landscape'
        );
    }

    public function exportReservationsExcel(Request $request)
    {
        $filters = $this->stayFilters($request);
        $reservations = $this->stayQuery($filters)->limit(2000)->get();
        $rows = [
            ['Booking', 'Guest', 'Phone', 'Email', 'Suite', 'Check-in', 'Check-out', 'Submitted', 'Created by', 'Rate', 'Came from', 'Status', 'Total', 'Balance', 'Currency'],
        ];
        foreach ($reservations as $booking) {
            $rows[] = [
                (string) $booking->id,
                $booking->names,
                $booking->phone,
                $booking->email,
                $booking->stayRoom()?->title ?? 'Unallocated',
                optional($booking->checkin_date)->format('Y-m-d'),
                optional($booking->checkout_date)->format('Y-m-d'),
                optional($booking->created_at)->format('Y-m-d H:i'),
                $booking->submittedByName(),
                $booking->staySummary(),
                $booking->channelLabel().' · '.$booking->sourceLabel(),
                ucwords(str_replace('_', ' ', $booking->deskState())),
                number_format((float) $booking->total_amount, 2, '.', ''),
                number_format((float) $booking->balance_amount, 2, '.', ''),
                strtoupper($booking->currencyKey()),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .'<Worksheet ss:Name="Reservations"><Table>';
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $cell, ENT_XML1).'</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="room-reservations.xls"',
        ]);
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
            'manual_discount_type' => 'nullable|in:percent,amount',
            'manual_discount_value' => 'nullable|numeric|min:0',
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
        $booking->room_id = $data['room_id'];
        $conflict = $this->stayConflict($booking, $data['checkin_date'], $data['checkout_date'], (int) $data['room_id']);
        if ($conflict) {
            return back()->withInput()->with('error', $conflict);
        }
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
        $conflict = $this->stayConflict($booking, $data['checkin_date'], $data['checkout_date'], (int) $data['room_id']);
        if ($conflict) {
            return back()->withInput()->with('error', $conflict);
        }
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

    public function receiptPdf(Booking $booking)
    {
        $this->assertRoomBooking($booking);
        $booking->load(['room', 'assignedRoom', 'payments.receiver', 'charges']);
        $setting = Setting::query()->first();

        return PdfFile::download(
            view('bills.pdf', [
                'title' => 'Invoice '.$booking->id,
                'body' => view('bills.stay-document', compact('booking', 'setting'))->render(),
            ])->render(),
            'invoice-'.$booking->id.'.pdf'
        );
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
        $overbooks = Schema::hasTable('room_overbooks')
            ? RoomOverbook::query()
                ->whereDate('starts_on', '<=', $dates->last()->toDateString())
                ->whereDate('ends_on', '>=', $start->toDateString())
                ->get()
            : collect();

        $blocks = $rooms->map(function (Room $room) use ($dates, $bookings, $closures, $overbooks) {
            $availability = $dates->map(function (Carbon $date) use ($room, $bookings, $closures, $overbooks) {
                $occupied = $bookings->filter(function (Booking $booking) use ($room, $date) {
                    $roomId = $booking->assigned_room_id ?: $booking->room_id;
                    if ((int) $roomId !== (int) $room->id || ! $booking->checkin_date || ! $booking->checkout_date) {
                        return false;
                    }

                    return $date->gte($booking->checkin_date) && $date->lt($booking->checkout_date);
                })->count();
                $closed = $closures->contains(function (RoomClosure $closure) use ($room, $date) {
                    return (int) $closure->room_id === (int) $room->id
                        && $date->gte($closure->starts_on)
                        && $date->lte($closure->ends_on);
                });
                if ($closed) {
                    return 'closed';
                }
                $extra = (int) $overbooks
                    ->filter(fn (RoomOverbook $overbook) => (int) $overbook->room_id === (int) $room->id
                        && $date->gte($overbook->starts_on)
                        && $date->lte($overbook->ends_on))
                    ->sum('extra');
                if ($extra > 0) {
                    return 'overbook';
                }

                return $occupied > 0 ? 'booked' : 'open';
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

    public function pos()
    {
        return view('content-management.front-office.pos');
    }

    public function posSales()
    {
        $ready = Schema::hasTable('restaurant_reservations');
        $menuItems = [];
        if ($ready && Schema::hasTable('restaurant_menu_items')) {
            foreach (RestaurantMenuItem::publicGroups() as $category => $rows) {
                foreach ($rows as $item) {
                    $menuItems[] = [
                        'id' => $item->id,
                        'category' => $category,
                        'category_label' => $item->categoryLabel(),
                        'name' => $item->name,
                        'description' => (string) $item->description,
                        'usd' => (float) $item->price_international,
                        'rwf' => (float) $item->price_local,
                    ];
                }
            }
        }

        return view('content-management.front-office.pos.sales', [
            'menuItems' => $menuItems,
            'oldCart' => collect(old('items', []))->map(fn ($qty) => (int) $qty)->filter(fn ($qty) => $qty > 0),
            'ready' => $ready,
        ]);
    }

    public function posInvoices(Request $request)
    {
        [$start, $end] = $this->posPeriod($request);
        $ready = Schema::hasTable('restaurant_reservations');
        $visits = $ready ? $this->diningVisits($start, $end, false) : collect();

        return view('content-management.front-office.pos.invoices', [
            'start' => $start,
            'end' => $end,
            'visits' => $visits,
            'tiers' => $ready ? $this->diningTierTotals($visits) : [],
            'ready' => $ready,
        ]);
    }

    public function posReports(Request $request)
    {
        [$start, $end] = $this->posPeriod($request);
        $ready = Schema::hasTable('restaurant_reservations') && Schema::hasTable('restaurant_reservation_items');
        $category = array_key_exists((string) $request->input('category'), RestaurantMenuItem::CATEGORIES)
            ? (string) $request->input('category')
            : '';
        $item = (string) $request->input('item', '');
        if (! str_starts_with($item, 'id:') && ! str_starts_with($item, 'name:')) {
            $item = '';
        }
        $menuItems = $ready && Schema::hasTable('restaurant_menu_items')
            ? RestaurantMenuItem::query()->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $lines = collect();
        $customItems = collect();
        if ($ready) {
            $sold = RestaurantReservationItem::query()
                ->whereHas('reservation', function ($query) use ($start, $end) {
                    $query->whereDate('reserved_for', '>=', $start)
                        ->whereDate('reserved_for', '<=', $end)
                        ->where('status', '!=', 'cancelled');
                });
            $customItems = (clone $sold)
                ->whereNull('menu_item_id')
                ->orderBy('name')
                ->get(['name', 'category'])
                ->unique(fn (RestaurantReservationItem $row) => $row->category.'|'.$row->name)
                ->values();
            if ($category !== '') {
                $sold->where('category', $category);
            }
            if (str_starts_with($item, 'id:')) {
                $sold->where('menu_item_id', (int) substr($item, 3));
            } elseif (str_starts_with($item, 'name:')) {
                $sold->where('name', rawurldecode(substr($item, 5)))->whereNull('menu_item_id');
            }
            $lines = $sold->with('reservation')->get()->sortByDesc(function (RestaurantReservationItem $line) {
                return optional($line->reservation?->reserved_for)->timestamp ?? 0;
            })->values();
        }
        $international = $lines->filter(fn (RestaurantReservationItem $line) => ! $line->reservation?->usesLocalPrices());
        $local = $lines->filter(fn (RestaurantReservationItem $line) => (bool) $line->reservation?->usesLocalPrices());
        $tierSummary = function ($rows): array {
            $total = round((float) $rows->sum('line_total'), 2);
            $paid = round((float) $rows->filter(fn (RestaurantReservationItem $line) => $line->reservation?->payment_status === 'paid')->sum('line_total'), 2);

            return [
                'total' => $total,
                'paid' => $paid,
                'due' => max(0, round($total - $paid, 2)),
            ];
        };

        return view('content-management.front-office.pos.reports', [
            'start' => $start,
            'end' => $end,
            'ready' => $ready,
            'category' => $category,
            'item' => $item,
            'menuItems' => $menuItems,
            'customItems' => $customItems,
            'lines' => $lines,
            'summary' => [
                'bills' => $lines->pluck('restaurant_reservation_id')->unique()->count(),
                'quantity' => (int) $lines->sum('quantity'),
                'international' => $tierSummary($international),
                'local' => $tierSummary($local),
            ],
        ]);
    }

    public function posSettings()
    {
        $ready = Schema::hasTable('restaurants');

        return view('content-management.front-office.pos.settings', [
            'ready' => $ready,
            'restaurant' => $ready ? Restaurant::access() : null,
            'reserveUrl' => $ready ? localized_route('dining.reserve') : '',
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
            ->where('category', 'service')
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->get();
        $setting = Setting::query()->first();
        $summaries = collect(['usd', 'rwf'])->map(function (string $currency) use ($bookings, $charges) {
            $rows = $bookings->filter(fn (Booking $booking) => $booking->currencyKey() === $currency);
            $posted = $charges->filter(fn (BookingCharge $charge) => ($charge->booking?->currencyKey() ?? 'usd') === $currency && ! $charge->included);

            return [
                'currency' => $currency,
                'total' => (float) $rows->sum('total_amount'),
                'services' => (float) $posted->sum('line_total'),
                'paid' => (float) $rows->sum('paid_amount'),
                'balance' => (float) $rows->sum('balance_amount'),
                'visible' => $rows->isNotEmpty() || $posted->isNotEmpty(),
            ];
        })->filter(fn (array $row) => $row['visible'])->values();
        if ($summaries->isEmpty()) {
            $summaries = collect([[
                'currency' => 'usd',
                'total' => 0.0,
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
        $menuItems = collect();
        if (Schema::hasTable('restaurant_menu_items')) {
            $menuItems = RestaurantMenuItem::query()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->groupBy('category');
        }

        return view('content-management.front-office.settings.extras', [
            'items' => $items,
            'menuItems' => $menuItems,
            'menuReady' => Schema::hasTable('restaurant_menu_items'),
            'section' => request('section') === 'menu' ? 'menu' : 'extras',
        ]);
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
        $overbooks = Schema::hasTable('room_overbooks')
            ? RoomOverbook::query()->with('room')->orderByDesc('starts_on')->get()
            : collect();
        $setting = Setting::query()->first();

        return view('content-management.front-office.settings.rates', compact('rooms', 'closures', 'discounts', 'overbooks', 'setting'));
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

    public function storeOverbook(Request $request)
    {
        RoomOverbook::query()->create($this->validatedOverbook($request));

        return redirect()
            ->to(url('/content-management/front-office/settings/rates').'#overbooks')
            ->with('success', 'Overbooking saved.');
    }

    public function updateOverbook(Request $request, RoomOverbook $overbook)
    {
        $overbook->update($this->validatedOverbook($request));

        return redirect()
            ->to(url('/content-management/front-office/settings/rates').'#overbooks')
            ->with('success', 'Overbooking updated.');
    }

    public function destroyOverbook(RoomOverbook $overbook)
    {
        $overbook->delete();

        return redirect()
            ->to(url('/content-management/front-office/settings/rates').'#overbooks')
            ->with('success', 'Overbooking removed.');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function posPeriod(Request $request): array
    {
        return [
            (string) $request->input('start_date', now()->startOfMonth()->toDateString()),
            (string) $request->input('end_date', now()->toDateString()),
        ];
    }

    private function diningVisits(string $start, string $end, bool $includeCancelled = false)
    {
        if (! Schema::hasTable('restaurant_reservations')) {
            return collect();
        }

        $query = RestaurantReservation::query()
            ->whereDate('reserved_for', '>=', $start)
            ->whereDate('reserved_for', '<=', $end)
            ->orderByDesc('reserved_for');
        if (! $includeCancelled) {
            $query->where('status', '!=', 'cancelled');
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RestaurantReservation>  $visits
     * @return list<array{tier: string, label: string, total: float, paid: float, balance: float, count: int}>
     */
    private function diningTierTotals($visits): array
    {
        return collect(['international' => 'International (USD)', 'local' => 'Local (RWF)'])->map(function (string $label, string $tier) use ($visits) {
            $rows = $visits->where('price_tier', $tier === 'local' ? 'local' : 'international');
            if ($tier === 'international') {
                $rows = $visits->filter(fn (RestaurantReservation $visit) => $visit->price_tier !== 'local');
            }
            $total = (float) $rows->sum('total_amount');
            $paid = (float) $rows->sum('paid_amount');

            return [
                'tier' => $tier,
                'label' => $label,
                'total' => $total,
                'paid' => $paid,
                'balance' => max(0, round($total - $paid, 2)),
                'count' => $rows->count(),
            ];
        })->filter(fn (array $row) => $row['count'] > 0)->values()->all();
    }

    /**
     * @return array{q: string, start_date: string, end_date: string}
     */
    private function stayFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'start_date' => $this->stayFilterDate($request->input('start_date')),
            'end_date' => $this->stayFilterDate($request->input('end_date')),
        ];
    }

    private function stayFilterDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  array{q: string, start_date: string, end_date: string}  $filters
     */
    private function stayQuery(array $filters)
    {
        $query = $this->roomBookings()->with(['room', 'assignedRoom', 'creator', 'user']);
        if ($filters['q'] !== '') {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($inner) use ($term, $filters) {
                $inner->where('names', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
                if (ctype_digit($filters['q'])) {
                    $inner->orWhere('id', (int) $filters['q']);
                }
            });
        }
        if ($filters['start_date'] !== '') {
            $query->whereDate('checkout_date', '>=', $filters['start_date']);
        }
        if ($filters['end_date'] !== '') {
            $query->whereDate('checkin_date', '<=', $filters['end_date']);
        }

        return $query->latest();
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
        $quote = $this->quoteStay($room, [
            'checkin_date' => Carbon::parse($booking->checkin_date)->toDateString(),
            'checkout_date' => Carbon::parse($booking->checkout_date)->toDateString(),
            'adults' => (int) $booking->adults,
            'children' => (int) ($booking->children ?? 0),
            'board' => $booking->board,
            'occupancy' => $booking->occupancy,
            'manual_discount_type' => $booking->manual_discount_type,
            'manual_discount_value' => $booking->manual_discount_value,
        ], $booking->currencyKey());
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

    private function stayConflict(Booking $booking, string $checkin, string $checkout, ?int $roomId = null): ?string
    {
        $roomId = $roomId ?: (int) ($booking->assigned_room_id ?: $booking->room_id);
        if (! $roomId) {
            return 'Choose a suite before changing these dates.';
        }
        $closed = RoomClosure::query()
            ->where('room_id', $roomId)
            ->whereDate('starts_on', '<', $checkout)
            ->whereDate('ends_on', '>=', $checkin)
            ->exists();
        if ($closed) {
            return 'The suite is closed for one of these nights.';
        }

        $others = $this->overlapping(Carbon::parse($checkin), Carbon::parse($checkout));
        if ($booking->exists) {
            $others->where('id', '!=', $booking->id);
        }
        $others = $others->get()->filter(function (Booking $other) use ($roomId) {
            return (int) ($other->assigned_room_id ?: $other->room_id) === $roomId;
        })->values();
        if ($others->isEmpty()) {
            return null;
        }

        $cursor = Carbon::parse($checkin)->startOfDay();
        $end = Carbon::parse($checkout)->startOfDay();
        while ($cursor->lt($end)) {
            $night = $cursor->toDateString();
            $occupied = $others->filter(function (Booking $other) use ($cursor) {
                return $other->checkin_date && $other->checkout_date
                    && $cursor->gte($other->checkin_date->copy()->startOfDay())
                    && $cursor->lt($other->checkout_date->copy()->startOfDay());
            })->count();
            $capacity = 1 + $this->overbookExtra($roomId, $night);
            if ($occupied >= $capacity) {
                $name = $others->first()->names;

                return $capacity > 1
                    ? $name.' already has this suite, and the overbook limit is full for '.$cursor->format('d M Y').'. Raise the extra bookings if another reservation should be taken.'
                    : $name.' already has this suite for those nights. Set the suite as overbooked for these dates if another reservation should be taken.';
            }
            $cursor->addDay();
        }

        return null;
    }

    private function overbookExtra(int $roomId, string $night): int
    {
        if (! Schema::hasTable('room_overbooks')) {
            return 0;
        }

        return (int) RoomOverbook::query()
            ->where('room_id', $roomId)
            ->whereDate('starts_on', '<=', $night)
            ->whereDate('ends_on', '>=', $night)
            ->sum('extra');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOverbook(Request $request): array
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'extra' => 'required|integer|min:1|max:20',
            'note' => 'nullable|string|max:255',
        ]);
        $data['extra'] = (int) $data['extra'];
        $data['note'] = filled($data['note'] ?? null) ? trim($data['note']) : null;

        return $data;
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
            'manual_discount_type' => 'nullable|in:percent,amount',
            'manual_discount_value' => 'nullable|numeric|min:0',
            'message' => 'nullable|string|max:1000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function quoteStay(Room $room, array $data, string $currency): array
    {
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

        return $this->applyDeskDiscount($quote, $data['manual_discount_type'] ?? null, $data['manual_discount_value'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function applyDeskDiscount(array $quote, mixed $type, mixed $value): array
    {
        $type = in_array($type, ['percent', 'amount'], true) ? $type : '';
        $amount = is_numeric($value) ? round((float) $value, 2) : 0.0;
        $nights = (int) ($quote['nights'] ?? 0);
        if ($type === '' || $amount <= 0 || $nights < 1) {
            return $quote;
        }
        $rackNightly = (float) ($quote['rack_nightly'] ?? $quote['nightly'] ?? 0);
        $rackTotal = round($rackNightly * $nights, 2);
        if ($type === 'percent') {
            $percent = min(100, $amount);
            $cut = round($rackTotal * ($percent / 100), 2);
            $label = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'% off';
        } else {
            $cut = min($rackTotal, $amount);
            $label = 'Amount off';
        }
        $total = max(0, round($rackTotal - $cut, 2));
        $quote['list_total'] = $rackTotal;
        $quote['discount_total'] = $cut;
        $quote['total'] = $total;
        $quote['nightly'] = $total / $nights;
        $quote['sale_nightly'] = $quote['nightly'];
        $quote['label'] = $label;
        $quote['has_discount'] = $cut >= 0.5;
        $quote['rack_nightly'] = $rackNightly;

        return $quote;
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
        $quote = $this->quoteStay($room, $data, $currency);
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
        $deskDiscount = $this->deskDiscount($data);
        $booking->manual_discount_type = $deskDiscount['type'];
        $booking->manual_discount_value = $deskDiscount['value'];
        $booking->message = $data['message'] ?? $booking->message;

        if (! $reprice) {
            $this->refreshBalance($booking);

            return;
        }

        $room = Room::query()->findOrFail($data['room_id']);
        $quote = $this->quoteStay($room, $data, $booking->currency);
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
            || $booking->currencyKey() !== (($data['currency'] ?? 'usd') === 'rwf' ? 'rwf' : 'usd')
            || (string) ($booking->manual_discount_type ?? '') !== (string) ($this->deskDiscount($data)['type'] ?? '')
            || round((float) ($booking->manual_discount_value ?? 0), 2) !== round((float) ($this->deskDiscount($data)['value'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: ?string, value: ?float}
     */
    private function deskDiscount(array $data): array
    {
        $type = in_array($data['manual_discount_type'] ?? '', ['percent', 'amount'], true)
            ? $data['manual_discount_type']
            : null;
        $value = is_numeric($data['manual_discount_value'] ?? null)
            ? round((float) $data['manual_discount_value'], 2)
            : null;
        if ($type === null || $value === null || $value <= 0) {
            return ['type' => null, 'value' => null];
        }
        if ($type === 'percent') {
            $value = min(100, $value);
        }

        return ['type' => $type, 'value' => $value];
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
