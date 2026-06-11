<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RecaptchaService
{
    public function verify(?string $token, string $expectedAction, ?string $remoteIp = null): void
    {
        $secret = (string) config('services.recaptcha.secret_key');

        if ($secret === '') {
            return;
        }

        if (! $token) {
            throw ValidationException::withMessages([
                'recaptcha_token' => ['reCAPTCHA verification is required.'],
            ]);
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]));

        $body = $response->json();
        $score = (float) data_get($body, 'score', 0);
        $minimumScore = (float) config('services.recaptcha.minimum_score', 0.5);

        if (
            ! $response->ok()
            || ! data_get($body, 'success')
            || data_get($body, 'action') !== $expectedAction
            || $score < $minimumScore
        ) {
            throw ValidationException::withMessages([
                'recaptcha_token' => ['reCAPTCHA verification failed. Please try again.'],
            ]);
        }
    }
}
