<?php

namespace App\Http\Controllers\Admin;

use App\Constants\DonationOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDonationFilterRequest;
use App\Http\Requests\RejectDonationRequest;
use App\Http\Requests\VerifyDonationRequest;
use App\Http\Resources\AdminDonationDetailResource;
use App\Http\Resources\AdminDonationResource;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendDonationRejectedJob;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DonationController extends Controller
{
    public function index(AdminDonationFilterRequest $request)
    {
        $data = $request->validated();

        $donations = Donation::query()
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($data['giving_method'] ?? null, fn ($query, $method) => $query->where('giving_method', $method))
            ->when($data['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('donor_name', 'like', "%{$search}%")
                        ->orWhere('donor_email', 'like', "%{$search}%")
                        ->orWhere('gateway_reference', 'like', "%{$search}%");
                });
            })
            ->with(['verifier:id,name,email', 'rejecter:id,name,email', 'proofDeleter:id,name,email'])
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return AdminDonationResource::collection($donations);
    }

    public function activity(): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('donations.view'), 403);

        $summary = Donation::query()
            ->selectRaw('count(*) as total_donations')
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as total_paid_amount', [DonationOptions::STATUS_PAID])
            ->selectRaw('sum(case when giving_method = ? and status = ? then 1 else 0 end) as pending_bank_transfers', [
                DonationOptions::METHOD_BANK_TRANSFER,
                DonationOptions::STATUS_UNDER_REVIEW,
            ])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_donations', [DonationOptions::STATUS_REJECTED])
            ->selectRaw('max(id) as latest_donation_id')
            ->selectRaw('max(updated_at) as latest_activity_at')
            ->first();

        $latestDonation = Donation::query()
            ->select(['uuid', 'donor_name', 'amount', 'category', 'giving_method', 'status', 'created_at'])
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'total_donations' => (int) $summary->total_donations,
                'total_paid_amount' => (float) $summary->total_paid_amount,
                'pending_bank_transfers' => (int) $summary->pending_bank_transfers,
                'rejected_donations' => (int) $summary->rejected_donations,
                'latest_donation_id' => $summary->latest_donation_id ? (int) $summary->latest_donation_id : null,
                'latest_activity_at' => $summary->latest_activity_at,
                'latest_donation' => $latestDonation ? [
                    'uuid' => $latestDonation->uuid,
                    'donor_name' => $latestDonation->donor_name,
                    'amount' => $latestDonation->amount,
                    'category' => $latestDonation->category,
                    'giving_method' => $latestDonation->giving_method,
                    'status' => $latestDonation->status,
                    'created_at' => $latestDonation->created_at,
                ] : null,
            ],
        ]);
    }

    public function show(Donation $donation): AdminDonationDetailResource
    {
        abort_unless(request()->user()->hasPermission('donations.view'), 403);

        return new AdminDonationDetailResource($donation->load(['events', 'verifier', 'rejecter', 'proofDeleter']));
    }

    public function verify(VerifyDonationRequest $request, Donation $donation): JsonResponse
    {
        abort_unless($donation->giving_method === DonationOptions::METHOD_BANK_TRANSFER, 422, 'Only bank transfer donations can be manually verified.');

        $donation->forceFill([
            'status' => DonationOptions::STATUS_PAID,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'paid_at' => $donation->paid_at ?? now(),
        ])->save();

        $donation->addEvent('bank_transfer_verified', 'Bank transfer verified by admin.', ['admin_id' => $request->user()->id]);
        $donation->deleteProofFile($request->user()->id, 'bank_transfer_proof_deleted_after_verification');
        SendDonationReceiptJob::dispatch($donation);
        Log::info('Bank transfer donation verified', ['donation_uuid' => $donation->uuid, 'admin_id' => $request->user()->id]);

        return response()->json(['status' => $donation->status]);
    }

    public function reject(RejectDonationRequest $request, Donation $donation): JsonResponse
    {
        abort_unless($donation->giving_method === DonationOptions::METHOD_BANK_TRANSFER, 422, 'Only bank transfer donations can be rejected.');

        $donation->forceFill([
            'status' => DonationOptions::STATUS_REJECTED,
            'rejected_reason' => $request->validated('rejected_reason'),
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
        ])->save();

        $donation->addEvent('bank_transfer_rejected', 'Bank transfer rejected by admin.', [
            'admin_id' => $request->user()->id,
            'reason' => $donation->rejected_reason,
        ]);
        $donation->deleteProofFile($request->user()->id, 'bank_transfer_proof_deleted_after_rejection');
        SendDonationRejectedJob::dispatch($donation);
        Log::info('Bank transfer donation rejected', ['donation_uuid' => $donation->uuid, 'admin_id' => $request->user()->id]);

        return response()->json(['status' => $donation->status]);
    }

    public function deleteProof(VerifyDonationRequest $request, Donation $donation): JsonResponse
    {
        abort_unless($request->user()->hasPermission('donations.delete_proof'), 403);

        $deleted = $donation->deleteProofFile($request->user()->id);

        return response()->json([
            'deleted' => $deleted,
        ]);
    }
}
