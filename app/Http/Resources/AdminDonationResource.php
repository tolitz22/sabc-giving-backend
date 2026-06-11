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
            'donor_email' => $this->donor_email,
            'amount' => $this->amount,
            'category' => $this->category,
            'giving_method' => $this->giving_method,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
