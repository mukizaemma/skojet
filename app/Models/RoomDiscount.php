<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomDiscount extends Model
{
    protected $fillable = [
        'room_id',
        'title',
        'discount_type',
        'amount',
        'starts_on',
        'ends_on',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function appliesToAllRooms(): bool
    {
        return $this->room_id === null;
    }
}
