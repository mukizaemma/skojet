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
        'price_rwf',
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
        'price_rwf' => 'float',
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
            $singleRwf = self::moneyOrNull($row['single_rwf'] ?? null);
            $doubleRwf = self::moneyOrNull($row['double_rwf'] ?? null);
            $valueRwf = $type === 'amount' ? self::moneyOrNull($row['discount_value_rwf'] ?? null) : null;
            $plans[$key] = [
                'single' => $single,
                'double' => $double,
                'single_rwf' => $singleRwf,
                'double_rwf' => $doubleRwf,
                'discount_type' => $type,
                'discount_value' => $value,
                'discount_value_rwf' => $valueRwf,
                'single_sale' => self::discounted($single, $type, $value),
                'double_sale' => self::discounted($double, $type, $value),
                'single_sale_rwf' => self::discounted($singleRwf, $type, $type === 'percent' ? $value : $valueRwf),
                'double_sale_rwf' => self::discounted($doubleRwf, $type, $type === 'percent' ? $value : $valueRwf),
            ];
        }

        return $plans;
    }

    /**
     * @return list<array{key: string, single: ?float, double: ?float, discount_type: string, discount_value: ?float, single_sale: ?float, double_sale: ?float}>
     */
    public function publicRatePlans(?string $currency = null): array
    {
        $currency = $currency === 'rwf' ? 'rwf' : 'usd';
        $plans = [];
        foreach ($this->ratePlans() as $key => $plan) {
            $projected = $currency === 'rwf'
                ? [
                    'single' => $plan['single_rwf'],
                    'double' => $plan['double_rwf'],
                    'single_sale' => $plan['single_sale_rwf'],
                    'double_sale' => $plan['double_sale_rwf'],
                    'discount_value' => $plan['discount_type'] === 'amount' ? $plan['discount_value_rwf'] : $plan['discount_value'],
                ]
                : [
                    'single' => $plan['single'],
                    'double' => $plan['double'],
                    'single_sale' => $plan['single_sale'],
                    'double_sale' => $plan['double_sale'],
                    'discount_value' => $plan['discount_value'],
                ];
            if ($projected['single'] === null && $projected['double'] === null) {
                continue;
            }
            $plans[] = [
                'key' => $key,
                'discount_type' => $plan['discount_type'],
                'discount_value_rwf' => $plan['discount_value_rwf'],
                'single_rwf' => $plan['single_rwf'],
                'double_rwf' => $plan['double_rwf'],
                'single_sale_rwf' => $plan['single_sale_rwf'],
                'double_sale_rwf' => $plan['double_sale_rwf'],
            ] + $projected;
        }

        return $plans;
    }

    public function headlineNightly(?string $currency = null): float
    {
        $currency = $currency === 'rwf' ? 'rwf' : 'usd';
        $prices = [];
        foreach ($this->publicRatePlans($currency) as $plan) {
            foreach (['single_sale', 'double_sale'] as $field) {
                if ($plan[$field] !== null) {
                    $prices[] = $plan[$field];
                }
            }
        }

        if ($prices !== []) {
            return (float) min($prices);
        }

        return (float) ($currency === 'rwf' ? ($this->price_rwf ?? 0) : ($this->price ?? 0));
    }

    /**
     * @return array{board: string, occupancy: string, rack: float, sale: float, discount_type: string, discount_value: ?float}|null
     */
    public function rateCell(?string $board, ?string $occupancy, ?string $currency = null): ?array
    {
        if (! isset(self::BOARD_PLANS[$board]) || ! in_array($occupancy, ['single', 'double'], true)) {
            return null;
        }

        $currency = $currency === 'rwf' ? 'rwf' : 'usd';
        $plan = $this->ratePlans()[$board];
        $rack = $currency === 'rwf' ? $plan[$occupancy.'_rwf'] : $plan[$occupancy];
        $sale = $currency === 'rwf' ? $plan[$occupancy.'_sale_rwf'] : $plan[$occupancy.'_sale'];
        if ($rack === null || $sale === null) {
            return null;
        }

        return [
            'board' => $board,
            'occupancy' => $occupancy,
            'rack' => (float) $rack,
            'sale' => (float) $sale,
            'discount_type' => $plan['discount_type'],
            'discount_value' => $currency === 'rwf' && $plan['discount_type'] === 'amount'
                ? $plan['discount_value_rwf']
                : $plan['discount_value'],
        ];
    }

    /**
     * @return array{board: string, occupancy: string, rack: float, sale: float, discount_type: string, discount_value: ?float}
     */
    public function resolveRate(?string $board, ?string $occupancy, string $prefer = 'double', ?string $currency = null): array
    {
        $currency = $currency === 'rwf' ? 'rwf' : 'usd';
        $chosen = $this->rateCell($board, $occupancy, $currency);
        if ($chosen) {
            return $chosen;
        }

        $explicit = filled($board) || filled($occupancy);
        $prefer = $prefer === 'single' ? 'single' : 'double';
        $best = null;
        $preferred = null;
        foreach ($this->publicRatePlans($currency) as $plan) {
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

        $price = (float) ($currency === 'rwf' ? ($this->price_rwf ?? 0) : ($this->price ?? 0));

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
