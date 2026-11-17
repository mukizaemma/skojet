<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const ARCHIVED = [
        'bill_item_removed',
        'room_charge_removed',
        'room_reservation_cancelled',
        'dining_reservation_cancelled',
        'reservation_deleted',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'summary',
        'payload',
        'ip',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'bill_item_removed' => 'Bill item removed',
            'room_charge_removed' => 'Room charge removed',
            'room_reservation_cancelled' => 'Room reservation cancelled',
            'dining_reservation_cancelled' => 'Dining reservation cancelled',
            'reservation_deleted' => 'Reservation deleted',
            default => ucwords(str_replace('_', ' ', (string) $this->action)),
        };
    }
}
