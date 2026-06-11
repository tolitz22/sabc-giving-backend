<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationEvent extends Model
{
    use HasFactory;

    protected $fillable = ['donation_id', 'event_type', 'description', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
