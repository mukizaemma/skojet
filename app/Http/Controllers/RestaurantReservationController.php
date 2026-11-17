<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantBillGuestMail;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantReservation;
use App\Models\RestaurantReservationItem;
use App\Services\SiteNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class RestaurantReservationController extends Controller
{
    public function index()
    {
        $reservations = RestaurantReservation::query()
            ->withCount('items')
            ->orderByDesc('reserved_for')
            ->orderByDesc('id')
            ->paginate(20);

        return view('content-management.restaurant.reservations', [
            'reservations' => $reservations,
        ]);
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

    public function updateStatus(Request $request, RestaurantReservation $reservation)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
        ]);
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
        $sent = $this->emailGuest($reservation);

        if ($reservation->accessGranted()) {
            $message = 'Payment received. Access is green. The guest was emailed the updated paid amount.';
        } else {
            $message = 'Marked as not received. The paid amount is zero and access stays yellow.';
        }

        if (! $sent) {
            return back()->with('warning', $message.' The email could not be sent. Check the mail settings and use Email the bill.');
        }

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
                ]);
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
            ]);
        }

        $reservation->recalculate();

        return back()->with('success', 'Item added to the bill.');
    }

    public function destroyItem(RestaurantReservation $reservation, RestaurantReservationItem $item)
    {
        abort_unless((int) $item->restaurant_reservation_id === (int) $reservation->id, 404);
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
}
