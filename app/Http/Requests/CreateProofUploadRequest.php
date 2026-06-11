<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProofUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', Rule::in([
                'image/jpeg',
                'image/png',
                'application/pdf',
            ])],
            'size' => ['required', 'integer', 'min:1', 'max:5242880'],
            'recaptcha_token' => ['nullable', 'string'],
        ];
    }
}
