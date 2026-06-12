<?php

namespace App\Http\Requests;

use App\Constants\DonationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDonationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('donations.view') === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(DonationOptions::STATUSES)],
            'category' => ['nullable', Rule::in(DonationOptions::CATEGORIES)],
            'giving_method' => ['nullable', Rule::in(DonationOptions::METHODS)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'timezone' => ['nullable', 'timezone'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
