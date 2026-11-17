<?php

namespace App\Livewire\Public;

use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantReservation;
use App\Models\Setting;
use App\Services\RestaurantReservationService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.frontbase')]
class RestaurantReservePage extends Component
{
    public string $price_tier = 'international';

    public string $menuQuery = '';

    public string $menuCategory = 'all';

    /** @var array<int, int> */
    public array $quantities = [];

    public string $names = '';

    public string $phone = '';

    public string $email = '';

    public string $reserved_date = '';

    public string $reserved_time = '';

    public int $guests = 2;

    public string $notes = '';

    public bool $pay_now = false;

    public string $payment_choice = 'minimum';

    public bool $payment_confirmed = false;

    public string $payer_name = '';

    public bool $submitted = false;

    public ?string $reference = null;

    public ?int $submittedReservationId = null;

    public bool $guestEmailSent = false;

    public ?string $whatsappUrl = null;

    public function mount(): void
    {
        $this->reserved_date = now()->toDateString();
        $this->reserved_time = '19:00';
    }

    public function setTier(string $tier): void
    {
        $this->price_tier = $tier === 'local' ? 'local' : 'international';
    }

    public function setMenuCategory(string $category): void
    {
        $this->menuCategory = array_key_exists($category, RestaurantMenuItem::CATEGORIES) ? $category : 'all';
    }

    public function setPayNow(bool $pay): void
    {
        $this->pay_now = $pay;
        if (! $pay) {
            $this->payment_confirmed = false;
            $this->payer_name = '';
        }
    }

    public function setPayment(string $choice): void
    {
        $this->payment_choice = $choice === 'full' ? 'full' : 'minimum';
    }

    public function addItem(int $id): void
    {
        $current = (int) ($this->quantities[$id] ?? 0);
        if ($current < 99) {
            $this->quantities[$id] = $current + 1;
        }
    }

    public function removeItem(int $id): void
    {
        $current = (int) ($this->quantities[$id] ?? 0);
        if ($current <= 1) {
            unset($this->quantities[$id]);

            return;
        }

        $this->quantities[$id] = $current - 1;
    }

    public function submit(string $via, RestaurantReservationService $reservations): void
    {
        $via = $via === 'whatsapp' ? 'whatsapp' : 'email';
        $rules = RestaurantReservationService::guestRules();
        if (! $this->pay_now) {
            unset($rules['payment_choice'], $rules['payment_confirmed'], $rules['payer_name']);
        }
        $this->validate($rules, [
            'payment_confirmed.accepted' => site_trans('dining.pay_confirm_error'),
            'payer_name.required' => site_trans('dining.pay_name_error'),
        ]);

        $order = $this->orderSummary();
        $minimum = Restaurant::access()->minimumFor($this->price_tier);
        if ($order['count'] < 1 || ($order['total'] + 0.001) < $minimum) {
            throw ValidationException::withMessages([
                'order' => site_trans('dining.reserve_minimum_error'),
            ]);
        }

        if ($via === 'whatsapp' && ! filled(hotel_whatsapp_digits(Setting::query()->first()))) {
            throw ValidationException::withMessages([
                'order' => site_trans('dining.reserve_whatsapp_unavailable'),
            ]);
        }

        if ($this->pay_now && Restaurant::access()->momoCode() === '') {
            throw ValidationException::withMessages([
                'order' => site_trans('dining.momo_missing'),
            ]);
        }

        $result = $reservations->submit([
            'names' => $this->names,
            'phone' => $this->phone,
            'email' => $this->email,
            'guests' => $this->guests,
            'reserved_for' => Carbon::parse($this->reserved_date.' '.$this->reserved_time),
            'notes' => $this->notes,
            'price_tier' => $this->price_tier,
            'submit_via' => $via,
            'pay_now' => $this->pay_now,
            'payment_choice' => $this->payment_choice,
            'payer_name' => $this->payer_name,
        ], $this->quantities);

        $this->reference = $result['reservation']->reference;
        $this->submittedReservationId = $result['reservation']->id;
        $this->guestEmailSent = (bool) $result['guest_sent'];
        $this->whatsappUrl = $result['whatsapp_url'];
        $this->submitted = true;
    }

    public function render()
    {
        $restaurant = Restaurant::access();
        $menu = RestaurantMenuItem::publicGroups();
        $query = mb_strtolower(trim($this->menuQuery));
        $visible = [];
        foreach ($menu as $category => $items) {
            if ($this->menuCategory !== 'all' && $this->menuCategory !== $category) {
                continue;
            }
            $rows = $items->filter(function (RestaurantMenuItem $item) use ($query) {
                if ($query === '') {
                    return true;
                }

                return str_contains(mb_strtolower($item->name.' '.$item->description), $query);
            })->values();
            if ($rows->isNotEmpty()) {
                $visible[$category] = $rows;
            }
        }
        $order = $this->orderSummary();
        $minimum = $restaurant->minimumFor($this->price_tier);
        $shortfall = max(0, round($minimum - $order['total'], 2));
        $contactReady = $this->contactReady();
        $minimumReached = $order['count'] > 0 && $shortfall < 0.001;
        $paymentReady = ! $this->pay_now || ($this->payment_confirmed && filled($this->payer_name) && $restaurant->momoCode() !== '');
        $submittedReservation = $this->submittedReservationId
            ? RestaurantReservation::query()->with('items')->find($this->submittedReservationId)
            : null;

        return view('frontend.restaurant-reserve', [
            'restaurant' => $restaurant,
            'menu' => $menu,
            'visibleMenu' => $visible,
            'orderLines' => $order['lines'],
            'orderTotal' => $order['total'],
            'minimum' => $minimum,
            'shortfall' => $shortfall,
            'canSend' => $contactReady && $minimumReached && $paymentReady,
            'whatsappReady' => filled(hotel_whatsapp_digits(Setting::query()->first())),
            'setting' => Setting::query()->first(),
            'submittedReservation' => $submittedReservation,
        ]);
    }

    private function contactReady(): bool
    {
        return filled($this->names)
            && filled($this->phone)
            && filled($this->email)
            && filled($this->reserved_date)
            && filled($this->reserved_time)
            && $this->guests >= 1;
    }

    /**
     * @return array{total: float, count: int, lines: list<array{name: string, quantity: int, line: float}>}
     */
    private function orderSummary(): array
    {
        $ids = [];
        foreach ($this->quantities as $id => $qty) {
            if ((int) $qty > 0) {
                $ids[] = (int) $id;
            }
        }

        if ($ids === []) {
            return ['total' => 0.0, 'count' => 0, 'lines' => []];
        }

        $items = RestaurantMenuItem::query()->whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');
        $lines = [];
        $total = 0.0;
        $count = 0;

        foreach ($ids as $id) {
            $item = $items->get($id);
            $qty = (int) ($this->quantities[$id] ?? 0);
            if (! $item || $qty < 1) {
                continue;
            }

            $line = round($item->priceFor($this->price_tier) * $qty, 2);
            $total += $line;
            $count += $qty;
            $lines[] = [
                'name' => $item->name,
                'quantity' => $qty,
                'line' => $line,
            ];
        }

        return [
            'total' => round($total, 2),
            'count' => $count,
            'lines' => $lines,
        ];
    }
}
