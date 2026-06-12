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
            'settlement' => $this->parseSettlementAmounts($checkoutAttributes),
        ];
    }

    private function parseSettlementAmounts(array $checkoutAttributes): array
    {
        $payment = data_get($checkoutAttributes, 'payments.0.attributes', []);

        return array_filter([
            'fee_amount' => $this->centavosToPesos(
                data_get($payment, 'fee')
                ?? data_get($payment, 'fees')
                ?? data_get($payment, 'fee_amount')
                ?? data_get($payment, 'balance_transaction.attributes.fee')
                ?? data_get($checkoutAttributes, 'fee')
                ?? data_get($checkoutAttributes, 'fee_amount')
            ),
            'tax_amount' => $this->centavosToPesos(
                data_get($payment, 'tax')
                ?? data_get($payment, 'tax_amount')
                ?? data_get($payment, 'withholding_tax')
                ?? data_get($payment, 'withholding_tax_amount')
                ?? data_get($payment, 'balance_transaction.attributes.tax')
                ?? data_get($payment, 'balance_transaction.attributes.tax_amount')
                ?? data_get($checkoutAttributes, 'tax')
                ?? data_get($checkoutAttributes, 'tax_amount')
                ?? data_get($checkoutAttributes, 'withholding_tax')
            ),
            'net_amount' => $this->centavosToPesos(
                data_get($payment, 'net_amount')
                ?? data_get($payment, 'net')
                ?? data_get($payment, 'settlement_amount')
                ?? data_get($payment, 'converted_net_amount')
                ?? data_get($payment, 'balance_transaction.attributes.net_amount')
                ?? data_get($payment, 'balance_transaction.attributes.net')
                ?? data_get($payment, 'balance_transaction.attributes.converted_net_amount')
                ?? data_get($checkoutAttributes, 'net_amount')
                ?? data_get($checkoutAttributes, 'settlement_amount')
                ?? data_get($checkoutAttributes, 'converted_net_amount')
            ),
        ], fn ($value) => $value !== null);
    }

    private function centavosToPesos(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(((float) $value) / 100, 2);
    }
}
