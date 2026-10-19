<?php

namespace App\Models;

use App\Models\Concerns\HasPublicThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;
    use HasPublicThumbnail;
    protected $table='rooms';

    protected $fillable = [
        'title',
        'slug',
        'room_number',
        'description',
        'image',
        'cover_image',
        'status',
        'room_status',
        'user_id',
        'category',
        'room_type',
        'number_of_rooms',
        'price',
        'couplePrice',
        'guests_included_in_price',
        'extra_adult_price',
        'extra_child_price',
        'extra_bed_price',
        'max_occupancy',
        'bed_count',
        'bed_type',
        'rate_plans',
        'amenity_id',
    ];

    protected $casts = [
        'rate_plans' => 'array',
    ];

    public const BED_TYPES = [
        'twin' => 'Twin Beds',
        'king' => 'King Size Bed',
        'double' => 'Double Bed',
        'deluxe' => 'Deluxe Bed',
    ];

    public const BOARD_PLANS = [
        'bed_breakfast' => 'Bed & breakfast',
        'half_board' => 'Half board',
        'full_board' => 'Full board',
    ];

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images(){
        return $this->hasMany(Roomimage::class);
    }

    public function bookings(){
        return $this->hasMany(Booking::class, 'assigned_room_id');
    }

    /**
     * Nightly rate for a guest mix: base price covers up to guests_included_in_price people
     * (adults first, then children). Extra adults/children pay their add-on rates; extra beds optional.
     */
    public function nightlyRateForGuests(int $adults, int $children, int $extraBeds = 0): float
    {
        $adults = max(0, $adults);
        $children = max(0, $children);
        $extraBeds = max(0, $extraBeds);

        $included = max(1, (int) ($this->guests_included_in_price ?? 2));
        $remaining = $included;
        $adultsCovered = min($adults, $remaining);
        $remaining -= $adultsCovered;
        $childrenCovered = min($children, $remaining);
        $extraAdults = $adults - $adultsCovered;
        $extraChildren = $children - $childrenCovered;

        $base = (float) ($this->price ?? 0);
        $ea = (float) ($this->extra_adult_price ?? 0);
        $ec = (float) ($this->extra_child_price ?? 0);
        $eb = (float) ($this->extra_bed_price ?? 0);

        return $base + ($extraAdults * $ea) + ($extraChildren * $ec) + ($extraBeds * $eb);
    }

    /**
     * @return array<string, array{single: ?float, double: ?float, discount_type: string, discount_value: ?float, single_sale: ?float, double_sale: ?float}>
     */
    public function ratePlans(): array
    {
        $stored = $this->rate_plans;
        if (! is_array($stored)) {
            $stored = [];
        }

        $plans = [];
        foreach (self::BOARD_PLANS as $key => $label) {
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $type = in_array($row['discount_type'] ?? '', ['amount', 'percent'], true) ? $row['discount_type'] : '';
            $value = self::moneyOrNull($row['discount_value'] ?? null);
            if ($type === '' || $value === null || $value <= 0) {
                $type = '';
                $value = null;
            }
            if ($type === 'percent' && $value !== null) {
                $value = min(100, $value);
            }
            $single = self::moneyOrNull($row['single'] ?? null);
            $double = self::moneyOrNull($row['double'] ?? null);
            $plans[$key] = [
                'single' => $single,
                'double' => $double,
                'discount_type' => $type,
                'discount_value' => $value,
                'single_sale' => self::discounted($single, $type, $value),
                'double_sale' => self::discounted($double, $type, $value),
            ];
        }

        return $plans;
    }

    /**
     * @return list<array{key: string, single: ?float, double: ?float, discount_type: string, discount_value: ?float, single_sale: ?float, double_sale: ?float}>
     */
    public function publicRatePlans(): array
    {
        $plans = [];
        foreach ($this->ratePlans() as $key => $plan) {
            if ($plan['single'] === null && $plan['double'] === null) {
                continue;
            }
            $plans[] = ['key' => $key] + $plan;
        }

        return $plans;
    }

    public function headlineNightly(): float
    {
        $prices = [];
        foreach ($this->publicRatePlans() as $plan) {
            foreach (['single_sale', 'double_sale'] as $field) {
                if ($plan[$field] !== null) {
                    $prices[] = $plan[$field];
                }
            }
        }

        if ($prices !== []) {
            return (float) min($prices);
        }

        return (float) ($this->price ?? 0);
    }

    /**
     * @return array{board: string, occupancy: string, rack: float, sale: float, discount_type: string, discount_value: ?float}|null
     */
    public function rateCell(?string $board, ?string $occupancy): ?array
    {
        if (! isset(self::BOARD_PLANS[$board]) || ! in_array($occupancy, ['single', 'double'], true)) {
            return null;
        }

        $plan = $this->ratePlans()[$board];
        if ($plan[$occupancy] === null) {
            return null;
        }

        return [
            'board' => $board,
            'occupancy' => $occupancy,
            'rack' => (float) $plan[$occupancy],
            'sale' => (float) $plan[$occupancy.'_sale'],
            'discount_type' => $plan['discount_type'],
            'discount_value' => $plan['discount_value'],
        ];
    }

    /**
     * @return array{board: string, occupancy: string, rack: float, sale: float, discount_type: string, discount_value: ?float}
     */
    public function resolveRate(?string $board, ?string $occupancy, string $prefer = 'double'): array
    {
        $chosen = $this->rateCell($board, $occupancy);
        if ($chosen) {
            return $chosen;
        }

        $explicit = filled($board) || filled($occupancy);
        $prefer = $prefer === 'single' ? 'single' : 'double';
        $best = null;
        $preferred = null;
        foreach ($this->publicRatePlans() as $plan) {
            foreach (['single', 'double'] as $occupancyKey) {
                if ($plan[$occupancyKey.'_sale'] === null) {
                    continue;
                }
                $candidate = [
                    'board' => $plan['key'],
                    'occupancy' => $occupancyKey,
                    'rack' => (float) $plan[$occupancyKey],
                    'sale' => (float) $plan[$occupancyKey.'_sale'],
                    'discount_type' => $plan['discount_type'],
                    'discount_value' => $plan['discount_value'],
                ];
                if ($best === null || $candidate['sale'] < $best['sale']) {
                    $best = $candidate;
                }
                if ($occupancyKey === $prefer && ($preferred === null || $candidate['sale'] < $preferred['sale'])) {
                    $preferred = $candidate;
                }
            }
        }

        if (! $explicit && $preferred) {
            return $preferred;
        }
        if ($best) {
            return $best;
        }

        $price = (float) ($this->price ?? 0);

        return [
            'board' => '',
            'occupancy' => '',
            'rack' => $price,
            'sale' => $price,
            'discount_type' => '',
            'discount_value' => null,
        ];
    }

    protected static function moneyOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return $number < 0 ? null : $number;
    }

    protected static function discounted(?float $rack, string $type, ?float $value): ?float
    {
        if ($rack === null) {
            return null;
        }
        if ($type === 'percent' && $value !== null) {
            return round($rack * (1 - (min(100, $value) / 100)), 2);
        }
        if ($type === 'amount' && $value !== null) {
            return round(max(0, $rack - $value), 2);
        }

        return $rack;
    }
}
