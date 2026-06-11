<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'instructions' => $this->instructions,
            'is_enabled' => $this->is_enabled,
            'sort_order' => $this->sort_order,
        ];

        if ($request->user()?->isActiveAdmin()) {
            $data['created_by'] = $this->creator?->only(['id', 'name', 'email']);
            $data['created_at'] = $this->created_at;
            $data['updated_at'] = $this->updated_at;
        }

        return $data;
    }
}
