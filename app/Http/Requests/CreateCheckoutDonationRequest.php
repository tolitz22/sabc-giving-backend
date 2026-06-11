<?php

namespace App\Http\Requests;

use App\Constants\DonationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donor_name' => ['required', 'string', 'max:255'],
            'donor_email' => ['required', 'email', 'max:255'],
            'donor_mobile' => ['nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'amount' => ['required', 'numeric', 'min:20'],
            'category' => ['required', Rule::in(DonationOptions::CATEGORIES)],
            'giving_method' => ['required', Rule::in([DonationOptions::METHOD_CARD, DonationOptions::METHOD_EWALLET])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
