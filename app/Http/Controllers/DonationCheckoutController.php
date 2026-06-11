<?php

namespace App\Http\Controllers;

use App\Constants\DonationOptions;
use App\Http\Requests\CreateCheckoutDonationRequest;
use App\Models\Donation;
use App\Services\PaymongoService;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DonationCheckoutController extends Controller
{
    public function __invoke(CreateCheckoutDonationRequest $request, PaymongoService $paymongo, RecaptchaService $recaptcha): JsonResponse
    {
        $data = $request->validated();
        $recaptcha->verify($data['recaptcha_token'] ?? null, 'checkout_create', $request->ip());

        $donation = Donation::create([
            'donor_name' => $data['donor_name'],
            'donor_email' => $data['donor_email'],
            'donor_mobile' => $data['donor_mobile'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'amount' => $data['amount'],
            'category' => $data['category'],
            'giving_method' => $data['giving_method'],
            'note' => $data['note'] ?? null,
            'status' => DonationOptions::STATUS_PENDING,
            'gateway' => 'paymongo',
        ]);

        $checkout = $paymongo->createCheckoutSession($donation);
        $attributes = data_get($checkout, 'data.attributes', []);

        $donation->forceFill([
            'gateway_checkout_id' => data_get($checkout, 'data.id'),
            'metadata' => array_merge($donation->metadata ?? [], ['checkout' => $checkout]),
        ])->save();

        $donation->addEvent('checkout_created', 'PayMongo hosted checkout session created.', ['checkout_id' => $donation->gateway_checkout_id]);
        Log::info('Checkout session created', ['donation_uuid' => $donation->uuid, 'checkout_id' => $donation->gateway_checkout_id]);

        return response()->json([
            'uuid' => $donation->uuid,
            'status' => $donation->status,
            'checkout_url' => $attributes['checkout_url'] ?? null,
        ], 201);
    }
}
