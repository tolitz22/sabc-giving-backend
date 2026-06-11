<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Donation;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('donations:delete-expired-proofs', function () {
    $count = 0;

    Donation::query()
        ->whereNotNull('proof_file_path')
        ->whereNotNull('proof_expires_at')
        ->where('proof_expires_at', '<=', now())
        ->each(function (Donation $donation) use (&$count) {
            if ($donation->deleteProofFile(eventType: 'bank_transfer_proof_expired')) {
                $count++;
            }
        });

    $this->info("Deleted {$count} expired proof file(s).");
})->purpose('Delete expired temporary bank transfer proof files.')->daily();
