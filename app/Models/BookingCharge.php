<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCharge extends Model
{
    protected $fillable = [
        'booking_id',
        'extra_item_id',
        'name',
        'category',
        'kind',
        'quantity',
        'unit_price',
        'sale_price',
        'included',
        'line_total',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'sale_price' => 'float',
        'included' => 'boolean',
        'line_total' => 'float',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ExtraItem::class, 'extra_item_id');
    }

    public function categoryLabel(): string
    {
        return ExtraItem::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}
