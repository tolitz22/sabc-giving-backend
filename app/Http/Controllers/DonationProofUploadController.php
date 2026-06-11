<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProofUploadRequest;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DonationProofUploadController extends Controller
{
    public function __invoke(CreateProofUploadRequest $request, RecaptchaService $recaptcha): JsonResponse
    {
        $data = $request->validated();
        $recaptcha->verify($data['recaptcha_token'] ?? null, 'proof_upload', $request->ip());

        $extension = match ($data['content_type']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        };

        $path = sprintf(
            'donations/tmp/%s/%s.%s',
            now()->format('Y/m/d'),
            (string) Str::uuid(),
            $extension
        );

        $upload = Storage::disk(config('filesystems.default'))->temporaryUploadUrl(
            $path,
            now()->addMinutes(15),
            [
                'ContentType' => $data['content_type'],
            ]
        );

        return response()->json([
            'key' => $path,
            'url' => $upload['url'],
            'headers' => $upload['headers'],
            'expires_at' => now()->addMinutes(15)->toISOString(),
        ]);
    }
}
