<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantReservationItem extends Model
{
    protected $fillable = [
        'restaurant_reservation_id',
        'menu_item_id',
        'category',
        'name',
        'description',
        'unit_price',
        'quantity',
        'line_total',
        'source',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'quantity' => 'integer',
        'line_total' => 'float',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(RestaurantReservation::class, 'restaurant_reservation_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuItem::class, 'menu_item_id');
    }
}
