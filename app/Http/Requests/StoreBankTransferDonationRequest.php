<?php

namespace App\Http\Requests;

use App\Constants\DonationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankTransferDonationRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'transfer_date' => ['required', 'date'],
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_file_path' => ['required', 'string', 'regex:/^donations\/tmp\/\d{4}\/\d{2}\/\d{2}\/[0-9a-fA-F-]+\.(jpg|png|pdf)$/'],
            'proof_original_name' => ['required', 'string', 'max:255'],
            'proof_mime_type' => ['required', 'string', 'in:image/jpeg,image/png,application/pdf'],
            'proof_size' => ['required', 'integer', 'min:1', 'max:5242880'],
            'recaptcha_token' => ['nullable', 'string'],
        ];
    }
}
