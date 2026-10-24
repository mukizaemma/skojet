<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactEnquiryAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Message $enquiry)
    {
    }

    public function build(): self
    {
        $type = $this->enquiry->enquiry_type ?? 'general';

        $prefix = match ($type) {
            'proposal' => 'New proposal request',
            'activity' => 'Activity quotation',
            default => 'New website enquiry',
        };

        $adminTitle = match ($type) {
            'proposal' => 'New proposal request',
            'activity' => 'Activity quotation',
            default => 'New website enquiry',
        };
        $typeLabel = match ($type) {
            'room' => 'Room enquiry',
            'proposal' => 'Proposal (meetings / dining)',
            'activity' => 'Activity quotation',
            default => 'General information',
        };

        return $this->subject($prefix.' — '.$this->enquiry->names)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.contact-enquiry-admin', [
                'adminTitle' => $adminTitle,
                'typeLabel' => $typeLabel,
            ]);
    }
}
