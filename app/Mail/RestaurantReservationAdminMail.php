<?php

namespace App\Mail;

use App\Models\RestaurantReservation;
use App\Services\BookingSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestaurantReservationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantReservation $reservation)
    {
    }

    public function build(): self
    {
        $mailable = $this->subject('Restaurant reservation — '.$this->reservation->names.' ('.$this->reservation->reference.')')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.restaurant-reservation-admin', [
                'hotelName' => BookingSubmissionService::hotelName(),
            ]);

        if (filled($this->reservation->email)) {
            $mailable->replyTo($this->reservation->email, $this->reservation->names ?: $this->reservation->email);
        }

        return $mailable;
    }
}
