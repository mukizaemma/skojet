<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantBillGuestMail;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantReservation;
use App\Models\RestaurantReservationItem;
use App\Services\SiteNotificationMail;
use App\Support\DeskAudit;
use App\Support\PdfFile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestaurantReservationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->reservationFilters($request);
        $reservations = $this->reservationQuery($filters)
            ->paginate(20)
            ->withQueryString();

        return view('content-management.restaurant.reservations', [
            'reservations' => $reservations,
            'filters' => $filters,
        ]);
    }

    public function menu()
    {
        $items = [];
        foreach (RestaurantMenuItem::publicGroups() as $category => $rows) {
            foreach ($rows as $item) {
                $items[] = [
                    'id' => $item->id,
                    'category' => $category,
                    'name' => $item->name,
                    'description' => $item->description,
                    'usd' => $item->price_international,
                    'rwf' => $item->price_local,
                ];
            }
        }

        return response()->json([
            'categories' => RestaurantMenuItem::CATEGORIES,
            'items' => $items,
        ]);
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'names' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'guests' => 'nullable|integer|min:1|max:40',
            'reserved_date' => 'required|date',
            'reserved_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'price_tier' => 'required|in:international,local',
            'submit_via' => 'required|in:phone,desk',
            'paid_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*' => 'nullable|integer|min:0|max:99',
        ]);

        $restaurant = Restaurant::access();
        $tier = $data['price_tier'] === 'local' ? 'local' : 'international';
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        $when = Carbon::parse($data['reserved_date'].' '.substr($data['reserved_time'], 0, 5));
        $selected = collect($data['items'] ?? [])
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0);

        $reservation = DB::transaction(function () use ($data, $restaurant, $tier, $paid, $when, $selected) {
            $reservation = RestaurantReservation::query()->create([
                'reference' => 'TMP-'.Str::uuid(),
                'names' => trim($data['names']),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'guests' => max(1, (int) ($data['guests'] ?? 1)),
                'reserved_for' => $when,
                'submit_via' => $data['submit_via'],
                'price_tier' => $tier,
                'locale' => 'en',
                'status' => 'confirmed',
                'payment_status' => $paid > 0 ? 'paid' : 'unpaid',
                'paid_at' => $paid > 0 ? now() : null,
                'minimum_amount' => $restaurant->minimumFor($tier),
                'total_amount' => 0,
                'paid_amount' => $paid,
                'payment_choice' => 'full',
                'claimed_amount' => $paid,
                'momo_code' => $restaurant->momoCode() !== '' ? $restaurant->momoCode() : null,
                'momo_account_name' => $restaurant->momoAccountName() !== '' ? $restaurant->momoAccountName() : null,
                'access_token' => Str::random(48),
            ] + DeskAudit::owner('restaurant_reservations'));
            $reservation->reference = 'R-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT);
            $reservation->save();

            if ($selected->isNotEmpty()) {
                $menu = RestaurantMenuItem::query()->whereIn('id', $selected->keys())->where('is_active', true)->get()->keyBy('id');
                foreach ($selected as $id => $qty) {
                    $item = $menu->get((int) $id);
                    if (! $item) {
                        continue;
                    }
                    $unit = round($item->priceFor($tier), 2);
                    $reservation->items()->create([
                        'menu_item_id' => $item->id,
                        'category' => $item->category,
                        'name' => $item->name,
                        'description' => $item->description,
                        'unit_price' => $unit,
                        'quantity' => $qty,
                        'line_total' => round($unit * $qty, 2),
                        'source' => 'staff',
                    ] + DeskAudit::owner('restaurant_reservation_items'));
                }
                $reservation->recalculate();
            }

            return $reservation;
        });

        $paidNote = $paid > 0
            ? ' '.$reservation->money($paid).' is marked as paid.'
            : ' Nothing is marked as paid yet.';

        return redirect()
            ->route('content-management.restaurant-reservations')
            ->with('success', $reservation->reference.' is saved for '.$reservation->names.'.'.$paidNote);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->reservationFilters($request);
        $reservations = $this->reservationQuery($filters)->limit(2000)->get();

        return PdfFile::download(
            view('bills.pdf', [
                'title' => 'Restaurant reservations',
                'body' => view('content-management.restaurant.reservations-export', [
                    'reservations' => $reservations,
                    'filters' => $filters,
                ])->render(),
            ])->render(),
            'restaurant-reservations.pdf',
            'landscape'
        );
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->reservationFilters($request);
        $reservations = $this->reservationQuery($filters)->limit(2000)->get();
        $rows = [
            ['Reference', 'Guest', 'Phone', 'Email', 'When', 'Guests', 'Items', 'Total', 'Paid', 'Payment', 'Booked by', 'Currency'],
        ];
        foreach ($reservations as $reservation) {
            $rows[] = [
                $reservation->reference,
                $reservation->names,
                $reservation->phone,
                $reservation->email,
                optional($reservation->reserved_for)->format('Y-m-d H:i'),
                (string) $reservation->guests,
                (string) $reservation->items_count,
                number_format((float) $reservation->total_amount, 2, '.', ''),
                number_format((float) $reservation->paid_amount, 2, '.', ''),
                $reservation->accessGranted() ? 'Paid' : 'Not paid',
                $this->bookedByLabel($reservation->submit_via),
                $reservation->usesLocalPrices() ? 'RWF' : 'USD',
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
            'Content-Disposition' => 'attachment; filename="restaurant-reservations.xls"',
        ]);
    }

    public function storeDesk(Request $request)
    {
        $data = $request->validate([
            'names' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'guests' => 'nullable|integer|min:1|max:40',
            'price_tier' => 'required|in:international,local',
            'paid_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array',
            'items.*' => 'nullable|integer|min:0|max:99',
        ]);

        $selected = collect($data['items'] ?? [])
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0);
        if ($selected->isEmpty()) {
            return back()->withErrors(['items' => 'Add at least one item to the cart.'])->withInput();
        }

        $restaurant = Restaurant::access();
        $tier = $data['price_tier'] === 'local' ? 'local' : 'international';
        $reservation = DB::transaction(function () use ($data, $restaurant, $tier, $selected) {
            $reservation = RestaurantReservation::query()->create([
                'reference' => 'TMP-'.Str::uuid(),
                'names' => trim((string) ($data['names'] ?? '')) !== '' ? trim((string) $data['names']) : 'Walk-in',
                'phone' => trim((string) ($data['phone'] ?? '')),
                'email' => '',
                'guests' => max(1, (int) ($data['guests'] ?? 1)),
                'reserved_for' => now(),
                'submit_via' => 'desk',
                'price_tier' => $tier,
                'locale' => 'en',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'minimum_amount' => $restaurant->minimumFor($tier),
                'total_amount' => 0,
                'paid_amount' => 0,
                'payment_choice' => 'full',
                'claimed_amount' => 0,
                'momo_code' => $restaurant->momoCode() !== '' ? $restaurant->momoCode() : null,
                'momo_account_name' => $restaurant->momoAccountName() !== '' ? $restaurant->momoAccountName() : null,
                'access_token' => Str::random(48),
            ] + DeskAudit::owner('restaurant_reservations'));
            $reservation->reference = 'R-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT);
            $reservation->save();

            $menu = RestaurantMenuItem::query()->whereIn('id', $selected->keys())->where('is_active', true)->get()->keyBy('id');
            foreach ($selected as $id => $qty) {
                $item = $menu->get((int) $id);
                if (! $item) {
                    continue;
                }
                $unit = round($item->priceFor($tier), 2);
                $reservation->items()->create([
                    'menu_item_id' => $item->id,
                    'category' => $item->category,
                    'name' => $item->name,
                    'description' => $item->description,
                    'unit_price' => $unit,
                    'quantity' => $qty,
                    'line_total' => round($unit * $qty, 2),
                    'source' => 'staff',
                ] + DeskAudit::owner('restaurant_reservation_items'));
            }
            $reservation->recalculate();

            $paid = round((float) ($data['paid_amount'] ?? 0), 2);
            $total = round((float) $reservation->total_amount, 2);
            if ($paid > $total) {
                $paid = $total;
            }
            $reservation->paid_amount = $paid;
            $reservation->claimed_amount = $paid;
            $reservation->payment_status = $paid > 0 ? 'paid' : 'unpaid';
            $reservation->paid_at = $paid > 0 ? now() : null;
            $reservation->save();

            return $reservation;
        });

        if ($reservation->items()->count() === 0) {
            $reservation->delete();

            return back()->withErrors(['items' => 'Add at least one item to the cart.'])->withInput();
        }

        $paidNote = (float) $reservation->paid_amount > 0
            ? ' '.$reservation->money((float) $reservation->paid_amount).' is marked as paid.'
            : ' Nothing is marked as paid yet.';

        return redirect()
            ->route('content-management.restaurant-reservations.show', $reservation)
            ->with('success', 'Bill '.$reservation->reference.' is open for '.$reservation->names.'.'.$paidNote);
    }

    public function show(RestaurantReservation $reservation)
    {
        $reservation->load('items');
        $menu = RestaurantMenuItem::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('content-management.restaurant.bill', [
            'reservation' => $reservation,
            'menu' => $menu,
            'billText' => $reservation->billText(),
            'guestBillText' => $this->textInGuestLocale($reservation),
        ]);
    }

    public function pdf(RestaurantReservation $reservation)
    {
        $reservation->load('items');

        return PdfFile::download(
            view('bills.pdf', [
                'title' => $reservation->reference,
                'body' => view('restaurant.bill-document', ['reservation' => $reservation])->render(),
            ])->render(),
            $reservation->reference.'.pdf'
        );
    }

    public function updateStatus(Request $request, RestaurantReservation $reservation)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'confirm_cancel' => 'nullable|accepted',
        ]);
        if ($data['status'] === 'cancelled' && $reservation->status !== 'cancelled') {
            if (($data['confirm_cancel'] ?? null) !== '1') {
                return back()->with('error', 'Confirm the cancellation before it can be saved.');
            }
            if (! DeskAudit::canRemove($reservation->created_by)) {
                return DeskAudit::denied();
            }
            if (! DeskAudit::ready()) {
                return back()->with('error', 'The audit archive is not ready. Run the database migration, then try again.');
            }
            DeskAudit::record(
                'dining_reservation_cancelled',
                'Cancelled dining reservation '.$reservation->reference.' for '.$reservation->names.'.',
                [
                    'reference' => $reservation->reference,
                    'guest' => $reservation->names,
                    'when' => optional($reservation->reserved_for)->format('Y-m-d H:i'),
                    'total' => (string) $reservation->total_amount,
                    'paid' => (string) $reservation->paid_amount,
                ],
                RestaurantReservation::class,
                (int) $reservation->id
            );
        }
        $reservation->status = $data['status'];
        $reservation->save();

        return back()->with('success', 'Reservation status updated.');
    }

    public function updatePayment(Request $request, RestaurantReservation $reservation)
    {
        $data = $request->validate([
            'received' => 'required|in:yes,no',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        if ($data['received'] === 'yes') {
            $amount = round((float) ($data['paid_amount'] ?? $reservation->claimed_amount), 2);
            if ($amount <= 0 && (float) $reservation->minimum_amount > 0) {
                return back()->with('error', 'Enter the amount that arrived. If nothing arrived, mark the money as not received.');
            }

            $reservation->payment_status = 'paid';
            $reservation->paid_amount = $amount;
            $reservation->paid_at = now();
        } else {
            $reservation->payment_status = 'unpaid';
            $reservation->paid_amount = 0;
            $reservation->paid_at = null;
        }

        $reservation->save();
        $reservation->load('items');

        if ($reservation->accessGranted()) {
            $message = 'Payment received. Access is green.';
        } else {
            $message = 'Marked as not received. The paid amount is zero and access stays yellow.';
        }

        if (! filled($reservation->email)) {
            return back()->with('success', $message);
        }

        $sent = $this->emailGuest($reservation);
        if (! $sent) {
            return back()->with('warning', $message.' The email could not be sent. Check the mail settings and use Email the bill.');
        }

        $message .= ' The guest was emailed the updated paid amount.';

        return back()->with('success', $message);
    }

    public function storeItem(Request $request, RestaurantReservation $reservation)
    {
        $data = $request->validate([
            'menu_item_id' => 'nullable|integer|exists:restaurant_menu_items,id',
            'custom_name' => 'nullable|string|max:120',
            'custom_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $quantity = (int) $data['quantity'];

        if (filled($data['menu_item_id'] ?? null)) {
            $menuItem = RestaurantMenuItem::query()->findOrFail($data['menu_item_id']);
            $existing = $reservation->items()->where('menu_item_id', $menuItem->id)->first();
            if ($existing) {
                $existing->quantity += $quantity;
                $existing->line_total = round((float) $existing->unit_price * $existing->quantity, 2);
                $existing->save();
            } else {
                $unit = round($menuItem->priceFor($reservation->price_tier), 2);
                $reservation->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'category' => $menuItem->category,
                    'name' => $menuItem->name,
                    'description' => $menuItem->description,
                    'unit_price' => $unit,
                    'quantity' => $quantity,
                    'line_total' => round($unit * $quantity, 2),
                    'source' => 'staff',
                ] + DeskAudit::owner('restaurant_reservation_items'));
            }
        } else {
            $name = trim((string) ($data['custom_name'] ?? ''));
            if ($name === '' || ! isset($data['custom_price'])) {
                return back()->withErrors(['custom_name' => 'Choose a menu item, or enter a name and price.'])->withInput();
            }

            $unit = round((float) $data['custom_price'], 2);
            $reservation->items()->create([
                'menu_item_id' => null,
                'category' => 'food',
                'name' => $name,
                'description' => null,
                'unit_price' => $unit,
                'quantity' => $quantity,
                'line_total' => round($unit * $quantity, 2),
                'source' => 'staff',
            ] + DeskAudit::owner('restaurant_reservation_items'));
        }

        $reservation->recalculate();

        return back()->with('success', 'Item added to the bill.');
    }

    public function destroyItem(Request $request, RestaurantReservation $reservation, RestaurantReservationItem $item)
    {
        abort_unless((int) $item->restaurant_reservation_id === (int) $reservation->id, 404);
        $request->validate([
            'confirm_remove' => 'accepted',
        ], [
            'confirm_remove.accepted' => 'Confirm the removal before it can be saved.',
        ]);
        if (! DeskAudit::canRemove($item->created_by)) {
            return DeskAudit::denied();
        }
        if (! DeskAudit::ready()) {
            return back()->with('error', 'The audit archive is not ready. Run the database migration, then try again.');
        }

        DeskAudit::record(
            'bill_item_removed',
            'Removed '.$item->quantity.' × '.$item->name.' from '.$reservation->reference.'.',
            [
                'bill' => $reservation->reference,
                'guest' => $reservation->names,
                'item' => $item->name,
                'quantity' => (string) $item->quantity,
                'amount' => (string) $item->line_total,
            ],
            RestaurantReservationItem::class,
            (int) $item->id
        );
        $item->delete();
        $reservation->recalculate();

        return back()->with('success', 'Item removed from the bill.');
    }

    public function share(RestaurantReservation $reservation)
    {
        if (! $reservation->isPaid()) {
            return back()->with('error', 'Mark the bill as paid before sending it to the guest.');
        }

        if ($reservation->submit_via !== 'email') {
            return back()->with('error', 'This guest asked for WhatsApp. Use the guest WhatsApp button.');
        }

        $reservation->load('items');
        $sent = $this->emailGuest($reservation);

        return back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'The paid bill was emailed to '.$reservation->email.'.'
                : 'The bill could not be emailed. Check the mail settings and try again.'
        );
    }

    private function emailGuest(RestaurantReservation $reservation): bool
    {
        $reservation->loadMissing('items');

        return $this->sendInGuestLocale($reservation, function () use ($reservation) {
            return SiteNotificationMail::sendToGuest($reservation->email, new RestaurantBillGuestMail($reservation));
        });
    }

    private function textInGuestLocale(RestaurantReservation $reservation): string
    {
        return $this->sendInGuestLocale($reservation, fn () => $reservation->billText());
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function sendInGuestLocale(RestaurantReservation $reservation, callable $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale($reservation->locale ?: 'en');

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * @return array{q: string, start_date: string, end_date: string}
     */
    private function reservationFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'start_date' => $this->filterDate($request->input('start_date')),
            'end_date' => $this->filterDate($request->input('end_date')),
        ];
    }

    private function filterDate(mixed $value): string
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
    private function reservationQuery(array $filters): Builder
    {
        $query = RestaurantReservation::query()->withCount('items');
        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('names', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }
        if ($filters['start_date'] !== '') {
            $query->whereDate('reserved_for', '>=', $filters['start_date']);
        }
        if ($filters['end_date'] !== '') {
            $query->whereDate('reserved_for', '<=', $filters['end_date']);
        }

        return $query->orderByDesc('reserved_for')->orderByDesc('id');
    }

    private function bookedByLabel(?string $via): string
    {
        return match ($via) {
            'whatsapp' => 'WhatsApp',
            'phone' => 'Phone',
            'desk' => 'At the desk',
            default => 'Email',
        };
    }
}
