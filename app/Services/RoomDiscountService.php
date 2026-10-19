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
    public function quote(Room $room, string $checkin, string $checkout, int $adults, int $children, int $roomCount = 1, int $extraBeds = 0): array
    {
        $nights = $this->nights($checkin, $checkout);
        $rooms = max(1, $roomCount);
        $adultsPerRoom = (int) max(1, ceil(max(1, $adults) / $rooms));
        $childrenPerRoom = (int) max(0, ceil(max(0, $children) / $rooms));
        $nightly = $room->nightlyRateForGuests($adultsPerRoom, $childrenPerRoom, max(0, $extraBeds));
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
                $cut = $this->nightSaving($offer, $nightly);
                if ($cut > $best) {
                    $best = $cut;
                    $bestLabel = $offer->title;
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
            'has_discount' => $saved >= 0.5,
        ];
    }

    /**
     * @return Collection<int, RoomDiscount>
     */
    protected function offersFor(Room $room, string $checkin, string $checkout): Collection
    {
        return RoomDiscount::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($room) {
                $query->whereNull('room_id')->orWhere('room_id', $room->id);
            })
            ->whereDate('starts_on', '<=', $checkout)
            ->whereDate('ends_on', '>=', $checkin)
            ->get();
    }

    protected function nightSaving(RoomDiscount $offer, float $nightly): float
    {
        if ($offer->discount_type === 'percent') {
            $percent = min(100, max(0, (float) $offer->amount));

            return $nightly * ($percent / 100);
        }

        return min($nightly, max(0, (float) $offer->amount));
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
