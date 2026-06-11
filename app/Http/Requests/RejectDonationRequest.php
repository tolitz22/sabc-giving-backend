<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('donations.reject') === true;
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
