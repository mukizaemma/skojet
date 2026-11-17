<?php

namespace App\Services;

use App\Mail\RestaurantReservationAdminMail;
use App\Mail\RestaurantReservationGuestMail;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantReservation;
use App\Models\Setting;
use App\Rules\ActiveEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantReservationService
{
    /**
     * @param  array<string, mixed>  $guest
     * @param  array<int|string, mixed>  $quantities
     * @return array{reservation: RestaurantReservation, admin_sent: bool, guest_sent: bool, whatsapp_url: ?string}
     */
    public function submit(array $guest, array $quantities): array
    {
        $reservation = $this->create($guest, $quantities);
        $reservation->load('items');

        $adminSent = SiteNotificationMail::sendToTeam(new RestaurantReservationAdminMail($reservation));
        $guestSent = SiteNotificationMail::sendToGuest(
            $reservation->email,
            new RestaurantReservationGuestMail($reservation)
        );

        $whatsappUrl = null;
        if ($reservation->submit_via === 'whatsapp') {
            $whatsappUrl = $this->hotelWhatsappUrl($reservation);
        }

        return [
            'reservation' => $reservation,
            'admin_sent' => $adminSent,
            'guest_sent' => $guestSent,
            'whatsapp_url' => $whatsappUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $guest
     * @param  array<int|string, mixed>  $quantities
     */
    public function create(array $guest, array $quantities): RestaurantReservation
    {
        $tier = ($guest['price_tier'] ?? '') === 'local' ? 'local' : 'international';
        $via = ($guest['submit_via'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
        $restaurant = Restaurant::access();
        $minimum = $restaurant->minimumFor($tier);

        $selected = [];
        foreach ($quantities as $id => $qty) {
            $count = (int) $qty;
            if ($count > 0) {
                $selected[(int) $id] = min($count, 99);
            }
        }

        if ($selected === []) {
            throw ValidationException::withMessages([
                'order' => site_trans('dining.reserve_minimum_error'),
            ]);
        }

        return DB::transaction(function () use ($guest, $selected, $tier, $via, $minimum) {
            $menu = RestaurantMenuItem::query()
                ->where('is_active', true)
                ->whereIn('id', array_keys($selected))
                ->get()
                ->keyBy('id');

            $reservation = RestaurantReservation::query()->create([
                'reference' => 'TMP-'.str()->uuid(),
                'names' => trim((string) $guest['names']),
                'phone' => trim((string) $guest['phone']),
                'email' => trim((string) $guest['email']),
                'guests' => max(1, (int) ($guest['guests'] ?? 1)),
                'reserved_for' => $guest['reserved_for'],
                'notes' => filled($guest['notes'] ?? null) ? trim((string) $guest['notes']) : null,
                'submit_via' => $via,
                'price_tier' => $tier,
                'locale' => site_locale(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'minimum_amount' => $minimum,
                'total_amount' => 0,
            ]);
            $reservation->reference = 'R-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT);
            $reservation->save();

            foreach ($selected as $id => $qty) {
                $item = $menu->get($id);
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
                    'source' => 'guest',
                ]);
            }

            $reservation->recalculate();

            if ($reservation->items()->count() < 1 || ((float) $reservation->total_amount + 0.001) < $minimum) {
                throw ValidationException::withMessages([
                    'order' => site_trans('dining.reserve_minimum_error'),
                ]);
            }

            $paying = filter_var($guest['pay_now'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $choice = ($guest['payment_choice'] ?? '') === 'full' ? 'full' : 'minimum';
            $restaurant = Restaurant::access();
            $reservation->payment_choice = $paying ? $choice : 'later';
            $reservation->payer_name = $paying ? trim((string) ($guest['payer_name'] ?? '')) : null;
            $reservation->claimed_amount = ! $paying
                ? 0
                : ($choice === 'full' ? round((float) $reservation->total_amount, 2) : $minimum);
            $reservation->paid_amount = 0;
            $reservation->payment_status = 'unpaid';
            $reservation->momo_code = $restaurant->momoCode();
            $reservation->momo_account_name = $restaurant->momoAccountName() !== '' ? $restaurant->momoAccountName() : null;
            $reservation->access_token = \Illuminate\Support\Str::random(48);
            $reservation->save();

            return $reservation->fresh(['items']);
        });
    }

    public function hotelWhatsappUrl(RestaurantReservation $reservation): ?string
    {
        $digits = whatsapp_digits(hotel_whatsapp_digits(Setting::query()->first()));
        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($reservation->billText());
    }

    public static function guestRules(): array
    {
        return [
            'names' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => ['required', 'email', 'max:255', new ActiveEmail],
            'guests' => 'required|integer|min:1|max:40',
            'reserved_date' => 'required|date|after_or_equal:today',
            'reserved_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'notes' => 'nullable|string|max:1000',
            'price_tier' => 'required|in:international,local',
            'payment_choice' => 'required|in:minimum,full',
            'payment_confirmed' => 'accepted',
            'payer_name' => 'required|string|max:255',
        ];
    }
}
