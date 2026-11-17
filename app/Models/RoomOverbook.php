<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomOverbook extends Model
{
    protected $fillable = [
        'room_id',
        'starts_on',
        'ends_on',
        'extra',
        'note',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'extra' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
