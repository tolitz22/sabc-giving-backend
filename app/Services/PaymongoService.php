<?php

namespace App\Services;

use App\Constants\DonationOptions;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymongoService
{
    public function createCheckoutSession(Donation $donation): array
    {
        $methodTypes = $donation->giving_method === DonationOptions::METHOD_CARD
            ? ['card']
            : ['gcash', 'paymaya', 'qrph'];
        $baseUrl = preg_replace('#/v1$#', '/v2', rtrim((string) config('services.paymongo.base_url'), '/'));

        $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->post($baseUrl.'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $donation->donor_name,
                            'email' => $donation->donor_email,
                            'phone' => $donation->donor_mobile,
                        ],
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount' => (int) round((float) $donation->amount * 100),
                            'name' => 'Scripture Alone Baptist Church - '.$donation->category,
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => $methodTypes,
                        'success_url' => rtrim((string) config('app.frontend_url'), '/').'/give/success?donation='.$donation->uuid,
                        'cancel_url' => rtrim((string) config('app.frontend_url'), '/').'/give/cancelled?donation='.$donation->uuid,
                        'description' => 'Church giving: '.$donation->category,
                        'reference_number' => $donation->uuid,
                        'send_email_receipt' => true,
                        'metadata' => [
                            'donation_id' => (string) $donation->id,
                            'donation_uuid' => $donation->uuid,
                            'donor_email' => $donation->donor_email,
                            'category' => $donation->category,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo checkout creation failed: '.$response->body());
        }

        return $response->json();
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $signature = (string) $request->header('Paymongo-Signature', $request->header('paymongo-signature', ''));
        if ($signature === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            if ($key && $value) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        if (! $timestamp) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach (Arr::flatten([$parts['te'] ?? [], $parts['li'] ?? []]) as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parseCheckoutPaidEvent(array $payload): array
    {
        $attributes = data_get($payload, 'data.attributes', data_get($payload, 'data', []));
        $checkout = data_get($attributes, 'data.attributes', data_get($attributes, 'data', []));
        $checkoutAttributes = data_get($checkout, 'attributes', $checkout);

        return [
            'event_id' => data_get($payload, 'data.id') ?: data_get($payload, 'id'),
            'event_type' => data_get($attributes, 'type') ?: data_get($payload, 'data.type'),
            'checkout_id' => data_get($checkout, 'id') ?: data_get($attributes, 'data.id'),
            'payment_id' => data_get($checkoutAttributes, 'payments.0.id') ?: data_get($checkoutAttributes, 'payment_intent.id'),
            'reference' => data_get($checkoutAttributes, 'reference_number') ?: data_get($checkoutAttributes, 'payments.0.attributes.external_reference_number'),
            'donation_id' => data_get($checkoutAttributes, 'metadata.donation_id'),
            'donation_uuid' => data_get($checkoutAttributes, 'metadata.donation_uuid') ?: data_get($checkoutAttributes, 'reference_number'),
        ];
    }
}
