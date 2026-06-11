<?php

namespace App\Models;

use App\Constants\DonationOptions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Donation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'donor_name',
        'donor_email',
        'donor_mobile',
        'is_anonymous',
        'amount',
        'category',
        'giving_method',
        'note',
        'status',
        'gateway',
        'gateway_checkout_id',
        'gateway_payment_id',
        'gateway_reference',
        'proof_file_path',
        'proof_original_name',
        'proof_mime_type',
        'proof_size',
        'proof_expires_at',
        'proof_deleted_by',
        'proof_deleted_at',
        'verified_by',
        'verified_at',
        'paid_at',
        'rejected_reason',
        'rejected_by',
        'rejected_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'is_anonymous' => 'boolean',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'proof_expires_at' => 'datetime',
            'proof_deleted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function events(): HasMany
    {
        return $this->hasMany(DonationEvent::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function proofDeleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_deleted_by');
    }

    public function addEvent(string $type, ?string $description = null, ?array $payload = null): DonationEvent
    {
        return $this->events()->create([
            'event_type' => $type,
            'description' => $description,
            'payload' => $payload,
        ]);
    }

    public function donorDisplayName(): string
    {
        return $this->is_anonymous ? 'Anonymous donor' : $this->donor_name;
    }

    public function markPaid(?string $paymentId = null, ?string $reference = null): void
    {
        $this->forceFill([
            'status' => DonationOptions::STATUS_PAID,
            'gateway_payment_id' => $paymentId ?? $this->gateway_payment_id,
            'gateway_reference' => $reference ?? $this->gateway_reference,
            'paid_at' => $this->paid_at ?? now(),
        ])->save();
    }

    public function deleteProofFile(?int $adminId = null, string $eventType = 'proof_deleted'): bool
    {
        if (! $this->proof_file_path) {
            return false;
        }

        $path = $this->proof_file_path;

        Storage::disk(config('filesystems.default'))->delete($path);

        $this->forceFill([
            'proof_file_path' => null,
            'proof_original_name' => null,
            'proof_mime_type' => null,
            'proof_size' => null,
            'proof_expires_at' => null,
            'proof_deleted_by' => $adminId,
            'proof_deleted_at' => now(),
        ])->save();

        $this->addEvent($eventType, 'Temporary proof file deleted.', [
            'admin_id' => $adminId,
            'path' => $path,
        ]);

        return true;
    }
}
