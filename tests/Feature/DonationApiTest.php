<?php

namespace Tests\Feature;

use App\Constants\DonationOptions;
use App\Jobs\SendDonationConfirmationJob;
use App\Jobs\SendDonationRejectedJob;
use App\Mail\DonationConfirmationMail;
use App\Models\BankAccount;
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
        $bankAccount = BankAccount::create([
            'bank_name' => 'BDO',
            'account_name' => 'Scripture Alone Baptist Church',
            'account_number' => '1234567890',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/donations/bank-transfer', [
            'donor_name' => 'Juan Dela Cruz',
            'donor_email' => 'juan@example.com',
            'donor_mobile' => '09171234567',
            'amount' => 500,
            'category' => 'Missions',
            'bank_account_id' => $bankAccount->id,
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
        Queue::assertPushed(SendDonationConfirmationJob::class);
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
            'amount' => 750,
            'status' => DonationOptions::STATUS_PENDING,
            'gateway_checkout_id' => 'cs_test_123',
        ]);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
                && data_get($payload, 'data.attributes.line_items.0.amount') === 75000
                && data_get($payload, 'data.attributes.payment_method_types') === ['card']
                && data_get($payload, 'data.attributes.metadata.donor_email') === 'maria@example.com';
        });
    }

    public function test_webhook_marks_paymongo_donation_awaiting_settlement_until_admin_confirms_settlement(): void
    {
        Queue::fake();
        config(['services.paymongo.webhook_secret' => '']);

        $donation = Donation::factory()->create([
            'gateway' => 'paymongo',
            'gateway_checkout_id' => 'cs_test_123',
            'amount' => 5000,
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
                                [
                                    'id' => 'pay_123',
                                    'attributes' => [
                                        'fee' => 19000,
                                        'withholding_tax' => 0,
                                        'net_amount' => 476000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => DonationOptions::STATUS_AWAITING_SETTLEMENT,
            'gateway_payment_id' => 'pay_123',
            'amount' => 5000,
            'gateway_fee_amount' => 190,
            'gateway_tax_amount' => 0,
            'gateway_net_amount' => 4760,
        ]);
        Queue::assertPushed(SendDonationConfirmationJob::class);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/donations/{$donation->uuid}/settle")
            ->assertOk()
            ->assertJsonPath('status', DonationOptions::STATUS_PAID);

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => DonationOptions::STATUS_PAID,
        ]);
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

        Queue::assertNotPushed(SendDonationConfirmationJob::class);
        Queue::assertPushed(SendDonationRejectedJob::class);
        $this->assertNull($verifyDonation->fresh()->proof_file_path);
        $this->assertNull($rejectDonation->fresh()->proof_file_path);
    }

    public function test_donation_confirmation_email_renders_church_marketing_content(): void
    {
        $donation = Donation::factory()->bankTransferUnderReview()->create([
            'donor_name' => 'Juan Dela Cruz',
            'amount' => 500,
            'category' => 'Missions',
            'created_at' => '2026-06-12 11:22:00',
        ]);

        $html = (new DonationConfirmationMail($donation))->render();

        $this->assertStringContainsString('Thank you for giving, Juan Dela Cruz.', $html);
        $this->assertStringContainsString('worship, discipleship, missions, and gospel ministry', $html);
        $this->assertStringContainsString('Received for review', $html);
        $this->assertStringContainsString('PHP 500.00', $html);
        $this->assertStringContainsString('Jun 12, 2026 7:22 PM PHT', $html);
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

    public function test_treasurer_cannot_delete_a_proof(): void
    {
        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);

        $treasurer = User::factory()->create(['role' => User::ROLE_TREASURER]);
        Sanctum::actingAs($treasurer);

        Storage::disk('r2')->put('donations/2026/06/treasurer/proof.jpg', 'proof');

        $donation = Donation::factory()->bankTransferUnderReview()->create([
            'proof_file_path' => 'donations/2026/06/treasurer/proof.jpg',
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size' => 1000,
            'proof_expires_at' => now()->addDays(14),
        ]);

        $this->deleteJson("/api/admin/donations/{$donation->uuid}/proof")
            ->assertForbidden();

        Storage::disk('r2')->assertExists('donations/2026/06/treasurer/proof.jpg');
        $this->assertSame('donations/2026/06/treasurer/proof.jpg', $donation->fresh()->proof_file_path);
    }

    public function test_admin_report_summary_supports_filters_and_breakdowns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        Donation::factory()->paid()->create([
            'amount' => 1000,
            'gateway_fee_amount' => 50,
            'gateway_net_amount' => 950,
            'category' => 'Missions',
            'giving_method' => DonationOptions::METHOD_CARD,
            'created_at' => '2026-06-05 10:00:00',
            'paid_at' => '2026-06-05 10:30:00',
        ]);
        Donation::factory()->paid()->create([
            'amount' => 2500,
            'category' => 'Missions',
            'giving_method' => DonationOptions::METHOD_BANK_TRANSFER,
            'is_anonymous' => true,
            'created_at' => '2026-06-06 10:00:00',
            'paid_at' => '2026-06-06 10:30:00',
        ]);
        Donation::factory()->paid()->create([
            'amount' => 700,
            'category' => 'Benevolence',
            'created_at' => '2026-05-01 10:00:00',
            'paid_at' => '2026-05-01 10:30:00',
        ]);
        Donation::factory()->bankTransferUnderReview()->create([
            'category' => 'Missions',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->getJson('/api/admin/reports/summary?date_from=2026-06-01&date_to=2026-06-30&category=Missions')
            ->assertOk()
            ->assertJsonPath('data.total_donations', 3)
            ->assertJsonPath('data.total_paid_amount', 3450)
            ->assertJsonPath('data.average_paid_amount', 1725)
            ->assertJsonPath('data.pending_bank_transfers', 1)
            ->assertJsonPath('data.totals_by_category.0.category', 'Missions')
            ->assertJsonPath('data.monthly_totals.0.month', '2026-06')
            ->assertJsonPath('data.daily_totals.0.day', '2026-06-05')
            ->assertJsonPath('data.anonymous_totals.0.label', 'Identified')
            ->assertJsonPath('data.recent_largest_donations.0.amount', 2500);
    }

    public function test_admin_date_filters_use_requested_timezone_boundaries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $juneElevenLocal = Donation::factory()->paid()->create([
            'amount' => 500,
            'created_at' => '2026-06-11 10:00:00',
            'paid_at' => '2026-06-11 10:30:00',
        ]);
        $juneTwelveLocal = Donation::factory()->paid()->create([
            'amount' => 900,
            'created_at' => '2026-06-11 20:19:00',
            'paid_at' => '2026-06-11 20:30:00',
        ]);

        $this->getJson('/api/admin/donations?date_from=2026-06-11&date_to=2026-06-11&timezone=Asia/Singapore')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.uuid', $juneElevenLocal->uuid);

        $this->getJson('/api/admin/donations?date_from=2026-06-12&date_to=2026-06-12&timezone=Asia/Singapore')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.uuid', $juneTwelveLocal->uuid);

        $this->getJson('/api/admin/stats?date_from=2026-06-12&date_to=2026-06-12&timezone=Asia/Singapore')
            ->assertOk()
            ->assertJsonPath('data.total_donations', 1)
            ->assertJsonPath('data.total_paid_amount', 900);
    }

    public function test_admin_can_download_donation_report_as_csv_and_xlsx(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        Donation::factory()->paid()->create([
            'donor_name' => 'Private Donor',
            'donor_email' => 'private@example.com',
            'is_anonymous' => true,
            'amount' => 1200,
            'category' => 'Missions',
            'created_at' => '2026-06-05 10:00:00',
            'paid_at' => '2026-06-05 10:30:00',
        ]);

        $csv = $this->get('/api/admin/reports/donations/export.csv?date_from=2026-06-01&date_to=2026-06-30');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Donation UUID', $csvContent);
        $this->assertStringContainsString('Anonymous donor', $csvContent);
        $this->assertStringContainsString('Contact retained privately', $csvContent);
        $this->assertStringNotContainsString('private@example.com', $csvContent);

        $xlsx = $this->get('/api/admin/reports/donations/export.xlsx?date_from=2026-06-01&date_to=2026-06-30');
        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
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
