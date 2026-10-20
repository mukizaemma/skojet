<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;
        protected $table='bookings';

    protected $fillable = [
        'user_id',
        'names',
        'phone',
        'email',
        'country',
        'stay_reason',
        'party_type',
        'board',
        'occupancy',
        'address',
        'postal_code',
        'checkin',
        'checkout',
        'checkin_date',
        'checkout_date',
        'adults',
        'children',
        'others',
        'rooms',
        'message',
        'admin_reply',
        'admin_replied_at',
        'status',
        'room_id',
        'facility_id',
        'tour_activity_id',
        'reservation_type',
        'assigned_room_id',
        'total_amount',
        'list_amount',
        'discount_amount',
        'discount_label',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'booking_type',
        'checked_in_by',
        'checked_out_by',
        'checked_in_at',
        'checked_out_at',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'checkout_date' => 'date',
        'admin_replied_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room(){
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function facility(){
        return $this->belongsTo(\App\Models\Facility::class, 'facility_id');
    }

    public function tourActivity(){
        return $this->belongsTo(\App\Models\TourActivity::class, 'tour_activity_id');
    }

    public function assignedRoom(){
        return $this->belongsTo(Room::class, 'assigned_room_id');
    }

    public function checkedInBy(){
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(){
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function roomMovements(){
        return $this->hasMany(RoomMovement::class);
    }

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function stayRoom(): ?Room
    {
        return $this->assignedRoom ?? $this->room;
    }

    public function nights(): int
    {
        if (! $this->checkin_date || ! $this->checkout_date) {
            return 0;
        }

        return max(0, $this->checkin_date->diffInDays($this->checkout_date));
    }

    public function deskState(): string
    {
        if (in_array($this->status, ['cancelled', 'No Show'], true)) {
            return 'cancelled';
        }
        if ($this->checked_out_at) {
            return 'checked_out';
        }
        if ($this->checked_in_at) {
            return 'checked_in';
        }
        if ($this->status === 'confirmed') {
            return 'confirmed';
        }
        if ((float) $this->balance_amount > 0 && (float) $this->paid_amount > 0) {
            return 'incomplete';
        }

        return 'unallocated';
    }
}
