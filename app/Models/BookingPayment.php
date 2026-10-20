<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Bank transfer',
        'card' => 'Card terminal',
        'other' => 'Other',
    ];

    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'note',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
