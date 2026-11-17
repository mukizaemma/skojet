<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;
    
    protected $table = 'restaurants';

    protected $fillable = [
        'title',
        'description',
        'image',
        'cuisine_section_title',
        'cuisine_section_lead',
        'requires_reservation',
        'minimum_amount',
        'minimum_amount_local',
        'momo_code',
        'momo_account_name',
    ];

    protected $casts = [
        'requires_reservation' => 'boolean',
        'minimum_amount' => 'float',
        'minimum_amount_local' => 'float',
    ];

    public static function access(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'title' => 'The restaurant',
            'requires_reservation' => true,
            'minimum_amount' => 0,
            'minimum_amount_local' => 0,
        ]);
    }

    public function requiresReservation(): bool
    {
        return (bool) $this->requires_reservation;
    }

    public function minimumFor(string $tier): float
    {
        return round($tier === 'local' ? (float) $this->minimum_amount_local : (float) $this->minimum_amount, 2);
    }

    public function momoCode(): string
    {
        return trim((string) ($this->momo_code ?? ''));
    }

    public function momoAccountName(): string
    {
        return trim((string) ($this->momo_account_name ?? ''));
    }

    public function images()
    {
        return $this->hasMany(Restoimage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function cuisines()
    {
        return $this->hasMany(RestaurantCuisine::class)->orderBy('sort_order')->orderBy('id');
    }
}
