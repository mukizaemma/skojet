<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public const CHANNELS = [
        'walkin' => 'Walk-in',
        'website' => 'Website',
        'ota' => 'OTA',
        'phone' => 'Phone',
        'email' => 'Email',
        'social' => 'Social media',
    ];

    public const SOURCES = [
        'tour_guide' => 'Tour guide',
        'friend' => 'Friend',
        'direct' => 'Direct booking',
        'ota' => 'OTA',
        'unspecified' => 'Not specified',
    ];

    protected $table='bookings';

    protected $fillable = [
        'user_id',
        'created_by',
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
        'charges_amount',
        'waived_amount',
        'booked_checkin_date',
        'booked_checkout_date',
        'checkin_note',
        'checkout_note',
        'list_amount',
        'discount_amount',
        'discount_label',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'currency',
        'booking_type',
        'reservation_channel',
        'guest_source',
        'checked_in_by',
        'checked_out_by',
        'checked_in_at',
        'checked_out_at',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'checkout_date' => 'date',
        'booked_checkin_date' => 'date',
        'booked_checkout_date' => 'date',
        'charges_amount' => 'float',
        'waived_amount' => 'float',
        'admin_replied_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedByName(): string
    {
        if ($this->creator) {
            return $this->creator->name;
        }

        if ($this->user) {
            return $this->user->name;
        }

        return $this->booking_type === 'online' ? 'Website' : '—';
    }

    public function channelKey(): string
    {
        if (isset(self::CHANNELS[$this->reservation_channel])) {
            return $this->reservation_channel;
        }

        return $this->booking_type === 'online' ? 'website' : 'walkin';
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channelKey()];
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->guest_source] ?? self::SOURCES['unspecified'];
    }

    public function mealPlanLabel(): string
    {
        if (! filled($this->board)) {
            return '';
        }

        return Room::BOARD_PLANS[$this->board] ?? ucwords(str_replace('_', ' ', (string) $this->board));
    }

    public function occupancyLabel(): string
    {
        return match ((string) $this->occupancy) {
            'single' => 'Single',
            'double' => 'Double',
            default => '',
        };
    }

    public function isGroupStay(): bool
    {
        return ($this->party_type ?? '') === 'group' || (int) ($this->rooms ?? 1) > 1;
    }

    public function staySummary(): string
    {
        $parts = array_values(array_filter([
            $this->mealPlanLabel(),
            $this->occupancyLabel(),
            $this->isGroupStay() ? 'Group' : null,
        ]));

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function currencyKey(): string
    {
        return $this->currency === 'rwf' ? 'rwf' : 'usd';
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

    public function charges()
    {
        return $this->hasMany(BookingCharge::class);
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
