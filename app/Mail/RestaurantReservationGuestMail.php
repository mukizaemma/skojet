<?php

namespace App\Mail;

use App\Models\RestaurantReservation;
use App\Services\BookingSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestaurantReservationGuestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantReservation $reservation)
    {
    }

    public function build(): self
    {
        $from = config('mail.from.address');

        return $this->subject(site_trans('dining.mail_guest_subject', [
            'hotel' => BookingSubmissionService::hotelName(),
        ]))
            ->from($from, config('mail.from.name'))
            ->replyTo($from, config('mail.from.name'))
            ->view('emails.restaurant-reservation-guest', [
                'hotelName' => BookingSubmissionService::hotelName(),
            ]);
    }
}
