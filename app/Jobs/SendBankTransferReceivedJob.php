<?php

namespace App\Jobs;

use App\Mail\BankTransferReceivedMail;
use App\Models\Donation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBankTransferReceivedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Donation $donation)
    {
    }

    public function handle(): void
    {
        Mail::to($this->donation->donor_email)->send(new BankTransferReceivedMail($this->donation));
    }
}
