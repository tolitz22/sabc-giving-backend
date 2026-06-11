<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'donor_name' => $this->donor_name,
            'donor_display_name' => $this->donorDisplayName(),
            'donor_email' => $this->donor_email,
            'is_anonymous' => $this->is_anonymous,
            'amount' => $this->amount,
            'category' => $this->category,
            'giving_method' => $this->giving_method,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'verified_by' => $this->verifier?->only(['id', 'name', 'email']),
            'verified_at' => $this->verified_at,
            'rejected_by' => $this->rejecter?->only(['id', 'name', 'email']),
            'rejected_at' => $this->rejected_at,
            'rejected_reason' => $this->rejected_reason,
            'proof_deleted_by' => $this->proofDeleter?->only(['id', 'name', 'email']),
            'proof_deleted_at' => $this->proof_deleted_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
