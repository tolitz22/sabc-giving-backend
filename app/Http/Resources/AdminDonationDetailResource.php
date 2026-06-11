<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AdminDonationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'donor_name' => $this->donor_name,
            'donor_email' => $this->donor_email,
            'donor_mobile' => $this->donor_mobile,
            'amount' => $this->amount,
            'category' => $this->category,
            'giving_method' => $this->giving_method,
            'note' => $this->note,
            'status' => $this->status,
            'gateway' => $this->gateway,
            'gateway_checkout_id' => $this->gateway_checkout_id,
            'gateway_payment_id' => $this->gateway_payment_id,
            'gateway_reference' => $this->gateway_reference,
            'bank_transfer' => $this->giving_method === 'bank_transfer' ? [
                'bank_account_id' => data_get($this->metadata, 'bank_account_id'),
                'bank_name' => data_get($this->metadata, 'bank_name'),
                'account_name' => data_get($this->metadata, 'account_name'),
                'account_number' => data_get($this->metadata, 'account_number'),
                'transfer_date' => data_get($this->metadata, 'transfer_date'),
                'reference_number' => $this->gateway_reference,
            ] : null,
            'proof' => $this->proof_file_path ? [
                'original_name' => $this->proof_original_name,
                'mime_type' => $this->proof_mime_type,
                'size' => $this->proof_size,
                'expires_at' => $this->proof_expires_at,
                'temporary_url' => $request->user()?->hasPermission('proofs.view')
                    ? Storage::disk(config('filesystems.default'))->temporaryUrl($this->proof_file_path, now()->addMinutes(15))
                    : null,
            ] : null,
            'verified_by' => $this->verifier?->only(['id', 'name', 'email']),
            'verified_at' => $this->verified_at,
            'rejected_by' => $this->rejecter?->only(['id', 'name', 'email']),
            'rejected_at' => $this->rejected_at,
            'proof_deleted_by' => $this->proofDeleter?->only(['id', 'name', 'email']),
            'proof_deleted_at' => $this->proof_deleted_at,
            'paid_at' => $this->paid_at,
            'rejected_reason' => $this->rejected_reason,
            'metadata' => $this->metadata,
            'events' => $this->events->map(fn ($event) => [
                'event_type' => $event->event_type,
                'description' => $event->description,
                'payload' => $event->payload,
                'created_at' => $event->created_at,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
