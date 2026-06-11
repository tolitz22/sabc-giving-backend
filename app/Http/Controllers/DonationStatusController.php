<?php

namespace App\Http\Controllers;

use App\Http\Resources\DonationStatusResource;
use App\Models\Donation;

class DonationStatusController extends Controller
{
    public function __invoke(Donation $donation): DonationStatusResource
    {
        return new DonationStatusResource($donation);
    }
}
