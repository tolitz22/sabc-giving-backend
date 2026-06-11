<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DonationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Donation $donation)
    {
    }

    public function build(): self
    {
        return $this->subject('Donation verification needs attention')
            ->view('emails.donation-rejected');
    }
}
