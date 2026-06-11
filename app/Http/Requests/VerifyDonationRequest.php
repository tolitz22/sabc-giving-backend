<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('donations.verify') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
