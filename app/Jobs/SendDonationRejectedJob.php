<?php

namespace App\Jobs;

use App\Mail\DonationRejectedMail;
use App\Models\Donation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendDonationRejectedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Donation $donation)
    {
    }

    public function handle(): void
    {
        Mail::to($this->donation->donor_email)->send(new DonationRejectedMail($this->donation));
    }
}
