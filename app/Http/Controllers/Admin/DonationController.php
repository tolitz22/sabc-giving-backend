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
            ->latest()
            ->paginate($data['per_page'] ?? 15);

        return AdminDonationResource::collection($donations);
    }

    public function show(Donation $donation): AdminDonationDetailResource
    {
        return new AdminDonationDetailResource($donation->load(['events', 'verifier']));
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
        $deleted = $donation->deleteProofFile($request->user()->id);

        return response()->json([
            'deleted' => $deleted,
        ]);
    }
}
