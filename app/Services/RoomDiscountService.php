<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomDiscount;
use DateTime;
use Illuminate\Support\Collection;

class RoomDiscountService
{
    /**
     * Price a stay. An active discount can cover every suite or one suite.
     * It applies only to nights inside its start and end dates. When more
     * than one offer matches a night, the larger saving is used.
     *
     * @return array{
     *     nights: int,
     *     nightly: float,
     *     sale_nightly: float,
     *     list_total: float,
     *     discount_total: float,
     *     total: float,
     *     label: string,
     *     has_discount: bool
     * }
     */
    public function quote(Room $room, string $checkin, string $checkout, int $adults, int $children, int $roomCount = 1, int $extraBeds = 0, ?string $board = null, ?string $occupancy = null, ?string $currency = null): array
    {
        $currency = $currency === 'rwf' ? 'rwf' : ($currency === 'usd' ? 'usd' : hotel_currency());
        $nights = $this->nights($checkin, $checkout);
        $rooms = max(1, $roomCount);
        $adultsPerRoom = (int) max(1, ceil(max(1, $adults) / $rooms));
        $childrenPerRoom = (int) max(0, ceil(max(0, $children) / $rooms));
        $prefer = $adultsPerRoom <= 1 ? 'single' : 'double';
        $resolved = $room->publicRatePlans($currency) !== []
            ? $room->resolveRate($board, $occupancy, $prefer, $currency)
            : null;
        $nightly = $resolved
            ? (float) $resolved['sale']
            : ($currency === 'rwf'
                ? (float) ($room->price_rwf ?? 0)
                : $room->nightlyRateForGuests($adultsPerRoom, $childrenPerRoom, max(0, $extraBeds)));
        $list = $nightly * $nights * $rooms;

        $empty = [
            'nights' => $nights,
            'nightly' => $nightly,
            'sale_nightly' => $nightly,
            'list_total' => $list,
            'discount_total' => 0.0,
            'total' => $list,
            'label' => '',
            'has_discount' => false,
            'badge' => '',
            'board' => $resolved['board'] ?? '',
            'occupancy' => $resolved['occupancy'] ?? '',
            'rack_nightly' => $resolved['rack'] ?? $nightly,
        ];

        if ($nights < 1 || $nightly <= 0) {
            return $empty;
        }

        $offers = $this->offersFor($room, $checkin, $checkout);
        if ($offers->isEmpty()) {
            return $empty;
        }

        $saved = 0.0;
        $labels = [];
        $badgeOffer = null;
        $cursor = new DateTime($checkin);
        $stayEnd = new DateTime($checkout);

        while ($cursor < $stayEnd) {
            $best = 0.0;
            $bestLabel = null;
            $night = $cursor->format('Y-m-d');
            foreach ($offers as $offer) {
                $offerStart = $offer->starts_on->format('Y-m-d');
                $offerEnd = $offer->ends_on->format('Y-m-d');
                if ($night < $offerStart || $night > $offerEnd) {
                    continue;
                }
                $cut = $this->nightSaving($offer, $nightly, $currency);
                if ($cut > $best) {
                    $best = $cut;
                    $bestLabel = $offer->title;
                    $badgeOffer = $offer;
                }
            }
            $saved += $best * $rooms;
            if ($bestLabel) {
                $labels[$bestLabel] = true;
            }
            $cursor->modify('+1 day');
        }

        $total = max(0, $list - $saved);

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'sale_nightly' => $rooms > 0 && $nights > 0 ? $total / $nights / $rooms : $nightly,
            'list_total' => $list,
            'discount_total' => $saved,
            'total' => $total,
            'label' => implode(', ', array_keys($labels)),
            'badge' => count($labels) === 1 && $badgeOffer ? hotel_offer_badge($badgeOffer) : implode(', ', array_keys($labels)),
            'has_discount' => $saved >= 0.5,
            'board' => $resolved['board'] ?? '',
            'occupancy' => $resolved['occupancy'] ?? '',
            'rack_nightly' => $resolved['rack'] ?? $nightly,
        ];
    }

    /**
     * Lowest nightly the guest would pay, with an active date offer applied.
     *
     * @return array{list: float, sale: float, has_discount: bool, badge: string, label: string}
     */
    public function displayNightly(Room $room, ?string $currency = null, ?string $checkin = null, ?string $checkout = null): array
    {
        $checkin = $checkin ?: now()->toDateString();
        $checkout = $checkout ?: now()->parse($checkin)->addDay()->toDateString();
        $currency = $currency === 'rwf' ? 'rwf' : ($currency === 'usd' ? 'usd' : hotel_currency());
        $bestList = null;
        $bestSale = null;
        $badge = '';
        $label = '';
        $hasDiscount = false;
        $plans = $room->publicRatePlans($currency);

        $consider = function (array $quote) use (&$bestList, &$bestSale, &$badge, &$label, &$hasDiscount): void {
            $list = (float) $quote['nightly'];
            $sale = (float) (($quote['has_discount'] ?? false) ? $quote['sale_nightly'] : $quote['nightly']);
            if ($bestSale !== null && $sale >= $bestSale) {
                return;
            }
            $bestList = $list;
            $bestSale = $sale;
            $hasDiscount = (bool) ($quote['has_discount'] ?? false) && $sale + 0.5 < $list;
            $badge = $hasDiscount ? (string) ($quote['badge'] ?? '') : '';
            $label = $hasDiscount ? (string) ($quote['label'] ?? '') : '';
        };

        if ($plans === []) {
            $consider($this->quote($room, $checkin, $checkout, 2, 0, 1, 0, null, null, $currency));
        } else {
            foreach ($plans as $plan) {
                foreach (['single', 'double'] as $occupancy) {
                    if ($plan[$occupancy.'_sale'] === null) {
                        continue;
                    }
                    $consider($this->quote(
                        $room,
                        $checkin,
                        $checkout,
                        $occupancy === 'single' ? 1 : 2,
                        0,
                        1,
                        0,
                        $plan['key'],
                        $occupancy,
                        $currency
                    ));
                }
            }
        }

        return [
            'list' => (float) ($bestList ?? 0),
            'sale' => (float) ($bestSale ?? 0),
            'has_discount' => $hasDiscount,
            'badge' => $badge,
            'label' => $label,
        ];
    }

    protected function offersFor(Room $room, string $checkin, string $checkout): Collection
    {
        return hotel_discount_offers()->filter(function (RoomDiscount $offer) use ($room, $checkin, $checkout) {
            if ($offer->room_id !== null && (int) $offer->room_id !== (int) $room->id) {
                return false;
            }

            $starts = $offer->starts_on?->toDateString();
            $ends = $offer->ends_on?->toDateString();

            return $starts !== null && $ends !== null && $starts <= $checkout && $ends >= $checkin;
        })->values();
    }

    protected function nightSaving(RoomDiscount $offer, float $nightly, string $currency = 'usd'): float
    {
        if ($offer->discount_type === 'percent') {
            $percent = min(100, max(0, (float) $offer->amount));

            return $nightly * ($percent / 100);
        }

        $amount = $currency === 'rwf' ? $offer->amount_rwf : $offer->amount;
        if ($amount === null) {
            return 0.0;
        }

        return min($nightly, max(0, (float) $amount));
    }

    protected function nights(string $checkin, string $checkout): int
    {
        try {
            $start = new DateTime($checkin);
            $end = new DateTime($checkout);

            return max(0, (int) $start->diff($end)->days);
        } catch (\Throwable) {
            return 0;
        }
    }
}
