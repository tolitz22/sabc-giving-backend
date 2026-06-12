<?php

namespace App\Http\Controllers;

use App\Constants\DonationOptions;
use App\Jobs\SendDonationConfirmationJob;
use App\Models\Donation;
use App\Models\PaymentWebhookLog;
use App\Services\PaymongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymongoWebhookController extends Controller
{
    public function __invoke(Request $request, PaymongoService $paymongo): JsonResponse
    {
        $payload = $request->json()->all();
        $eventType = data_get($payload, 'data.attributes.type') ?: data_get($payload, 'data.type');

        $log = PaymentWebhookLog::create([
            'provider' => 'paymongo',
            'event_type' => $eventType,
            'event_id' => data_get($payload, 'data.id'),
            'payload' => $payload,
            'signature' => $request->header('Paymongo-Signature', $request->header('paymongo-signature')),
            'status' => 'received',
        ]);

        Log::info('PayMongo webhook received', ['event_type' => $eventType, 'event_id' => $log->event_id]);

        try {
            if (! $paymongo->verifyWebhookSignature($request)) {
                $log->update(['status' => 'invalid_signature', 'processed_at' => now()]);
                return response()->json(['message' => 'invalid signature'], 400);
            }

            if ($eventType === 'checkout_session.payment.paid') {
                $event = $paymongo->parseCheckoutPaidEvent($payload);
                $identifiers = array_filter([
                    'id' => $event['donation_id'],
                    'uuid' => $event['donation_uuid'],
                    'gateway_checkout_id' => $event['checkout_id'],
                ]);

                $donation = null;
                if ($identifiers !== []) {
                    $donation = Donation::query()
                        ->where(function ($query) use ($identifiers) {
                            foreach ($identifiers as $column => $value) {
                                $query->orWhere($column, $value);
                            }
                        })
                        ->first();
                }

                if ($donation && $donation->status !== DonationOptions::STATUS_PAID) {
                    $donation->markPaid($event['payment_id'], $event['reference']);
                    $donation->addEvent('payment_paid', 'PayMongo webhook marked donation as paid.', $event);
                    SendDonationConfirmationJob::dispatch($donation);
                    Log::info('Donation marked paid from PayMongo webhook', ['donation_uuid' => $donation->uuid]);
                }
            }

            $log->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            Log::error('PayMongo webhook processing failed', ['error' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
