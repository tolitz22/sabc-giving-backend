<?php

namespace Tests\Feature;

use App\Constants\DonationOptions;
use App\Jobs\SendBankTransferReceivedJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendDonationRejectedJob;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DonationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_bank_transfer_donation_moves_direct_upload_proof_and_sets_under_review(): void
    {
        Queue::fake();
        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);
        Storage::disk('r2')->put('donations/tmp/2026/06/11/019731b9-9b7a-7381-8a08-30cfb74aa5f6.jpg', 'proof');

        $response = $this->postJson('/api/donations/bank-transfer', [
            'donor_name' => 'Juan Dela Cruz',
            'donor_email' => 'juan@example.com',
            'donor_mobile' => '09171234567',
            'amount' => 500,
            'category' => 'Missions',
            'bank_name' => 'BDO',
            'transfer_date' => now()->toDateString(),
            'reference_number' => 'REF123',
            'proof_file_path' => 'donations/tmp/2026/06/11/019731b9-9b7a-7381-8a08-30cfb74aa5f6.jpg',
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size' => 1024,
        ]);

        $response->assertCreated()->assertJsonPath('status', DonationOptions::STATUS_UNDER_REVIEW);
        $donation = Donation::firstOrFail();

        Storage::disk('r2')->assertExists($donation->proof_file_path);
        Storage::disk('r2')->assertMissing('donations/tmp/2026/06/11/019731b9-9b7a-7381-8a08-30cfb74aa5f6.jpg');
        $this->assertNotNull($donation->proof_expires_at);
        Queue::assertPushed(SendBankTransferReceivedJob::class);
    }

    public function test_bank_transfer_requires_valid_direct_upload_proof_metadata(): void
    {
        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);

        $response = $this->postJson('/api/donations/bank-transfer', [
            'donor_name' => 'Juan Dela Cruz',
            'donor_email' => 'juan@example.com',
            'amount' => 500,
            'category' => 'Missions',
            'bank_name' => 'BDO',
            'transfer_date' => now()->toDateString(),
            'reference_number' => 'REF123',
            'proof_file_path' => '../malware.exe',
            'proof_original_name' => 'malware.exe',
            'proof_mime_type' => 'application/octet-stream',
            'proof_size' => 1024,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'proof_file_path',
            'proof_mime_type',
        ]);
    }

    public function test_creating_checkout_session_uses_paymongo_hosted_checkout(): void
    {
        Http::fake([
            'api.paymongo.com/*' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/donations/checkout', [
            'donor_name' => 'Maria Santos',
            'donor_email' => 'maria@example.com',
            'amount' => 750,
            'category' => 'Tithes & Offering',
            'giving_method' => 'card',
        ]);

        $response->assertCreated()->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/test');
        $this->assertDatabaseHas('donations', [
            'donor_email' => 'maria@example.com',
            'status' => DonationOptions::STATUS_PENDING,
            'gateway_checkout_id' => 'cs_test_123',
        ]);
    }

    public function test_webhook_marks_donation_as_paid(): void
    {
        Queue::fake();
        config(['services.paymongo.webhook_secret' => '']);

        $donation = Donation::factory()->create([
            'gateway' => 'paymongo',
            'gateway_checkout_id' => 'cs_test_123',
            'status' => DonationOptions::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/webhooks/paymongo', [
            'data' => [
                'id' => 'evt_123',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => 'cs_test_123',
                        'attributes' => [
                            'metadata' => [
                                'donation_id' => (string) $donation->id,
                                'donation_uuid' => $donation->uuid,
                            ],
                            'payments' => [
                                ['id' => 'pay_123'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => DonationOptions::STATUS_PAID,
            'gateway_payment_id' => 'pay_123',
        ]);
        Queue::assertPushed(SendDonationReceiptJob::class);
    }

    public function test_admin_can_list_verify_and_reject_donations(): void
    {
        Queue::fake();
        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        Donation::factory()->count(2)->create();
        $verifyDonation = Donation::factory()->bankTransferUnderReview()->create([
            'proof_file_path' => 'donations/2026/06/verify/proof.jpg',
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size' => 1000,
            'proof_expires_at' => now()->addDays(14),
        ]);
        $rejectDonation = Donation::factory()->bankTransferUnderReview()->create([
            'proof_file_path' => 'donations/2026/06/reject/proof.jpg',
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size' => 1000,
            'proof_expires_at' => now()->addDays(14),
        ]);

        $this->getJson('/api/admin/donations')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        $this->patchJson("/api/admin/donations/{$verifyDonation->uuid}/verify")
            ->assertOk()
            ->assertJsonPath('status', DonationOptions::STATUS_PAID);

        $this->patchJson("/api/admin/donations/{$rejectDonation->uuid}/reject", [
            'rejected_reason' => 'Reference number could not be matched.',
        ])->assertOk()->assertJsonPath('status', DonationOptions::STATUS_REJECTED);

        Queue::assertPushed(SendDonationReceiptJob::class);
        Queue::assertPushed(SendDonationRejectedJob::class);
        $this->assertNull($verifyDonation->fresh()->proof_file_path);
        $this->assertNull($rejectDonation->fresh()->proof_file_path);
    }

    public function test_admin_can_delete_a_proof_without_changing_donation_status(): void
    {
        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        Storage::disk('r2')->put('donations/2026/06/delete/proof.jpg', 'proof');

        $donation = Donation::factory()->bankTransferUnderReview()->create([
            'proof_file_path' => 'donations/2026/06/delete/proof.jpg',
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size' => 1000,
            'proof_expires_at' => now()->addDays(14),
        ]);

        $this->deleteJson("/api/admin/donations/{$donation->uuid}/proof")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        Storage::disk('r2')->assertMissing('donations/2026/06/delete/proof.jpg');
        $this->assertNull($donation->fresh()->proof_file_path);
        $this->assertSame(DonationOptions::STATUS_UNDER_REVIEW, $donation->fresh()->status);
    }

    public function test_public_status_endpoint_does_not_expose_sensitive_data(): void
    {
        $donation = Donation::factory()->create([
            'donor_email' => 'private@example.com',
            'proof_file_path' => 'donations/2026/01/test/proof.pdf',
        ]);

        $this->getJson("/api/donations/{$donation->uuid}")
            ->assertOk()
            ->assertJsonMissing(['donor_email' => 'private@example.com'])
            ->assertJsonMissing(['proof_file_path' => 'donations/2026/01/test/proof.pdf']);
    }
}
