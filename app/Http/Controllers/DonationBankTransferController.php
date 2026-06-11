<?php

namespace App\Http\Controllers;

use App\Constants\DonationOptions;
use App\Http\Requests\StoreBankTransferDonationRequest;
use App\Jobs\SendBankTransferReceivedJob;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DonationBankTransferController extends Controller
{
    public function __invoke(StoreBankTransferDonationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($data['proof_file_path']), 422, 'Uploaded proof file was not found. Please upload it again.');

        $donation = Donation::create([
            'donor_name' => $data['donor_name'],
            'donor_email' => $data['donor_email'],
            'donor_mobile' => $data['donor_mobile'] ?? null,
            'amount' => $data['amount'],
            'category' => $data['category'],
            'giving_method' => DonationOptions::METHOD_BANK_TRANSFER,
            'note' => $data['note'] ?? null,
            'status' => DonationOptions::STATUS_UNDER_REVIEW,
            'gateway_reference' => $data['reference_number'],
            'metadata' => [
                'bank_name' => $data['bank_name'],
                'transfer_date' => $data['transfer_date'],
            ],
        ]);

        $extension = pathinfo($data['proof_file_path'], PATHINFO_EXTENSION);
        $path = sprintf('donations/%s/%s/%s/proof-of-transfer.%s', now()->year, now()->format('m'), $donation->uuid, $extension);
        $disk->move($data['proof_file_path'], $path);

        $donation->forceFill([
            'proof_file_path' => $path,
            'proof_original_name' => $data['proof_original_name'],
            'proof_mime_type' => $data['proof_mime_type'],
            'proof_size' => $data['proof_size'],
            'proof_expires_at' => now()->addDays(14),
        ])->save();

        $donation->addEvent('bank_transfer_submitted', 'Bank transfer proof received for admin review.');
        SendBankTransferReceivedJob::dispatch($donation);
        Log::info('Bank transfer donation created', ['donation_uuid' => $donation->uuid]);

        return response()->json([
            'uuid' => $donation->uuid,
            'status' => $donation->status,
        ], 201);
    }
}
