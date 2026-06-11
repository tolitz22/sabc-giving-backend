<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
