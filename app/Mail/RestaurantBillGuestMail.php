<?php

namespace App\Mail;

use App\Models\RestaurantReservation;
use App\Services\BookingSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestaurantBillGuestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantReservation $reservation)
    {
    }

    public function build(): self
    {
        $from = config('mail.from.address');

        return $this->subject(site_trans('dining.mail_bill_subject', [
            'hotel' => BookingSubmissionService::hotelName(),
            'ref' => $this->reservation->reference,
            'amount' => $this->reservation->money((float) $this->reservation->paid_amount),
        ]))
            ->from($from, config('mail.from.name'))
            ->replyTo($from, config('mail.from.name'))
            ->view('emails.restaurant-bill-guest', [
                'hotelName' => BookingSubmissionService::hotelName(),
            ]);
    }
}
