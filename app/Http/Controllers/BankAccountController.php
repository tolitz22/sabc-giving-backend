<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;

class BankAccountController extends Controller
{
    public function __invoke()
    {
        return BankAccountResource::collection(
            BankAccount::query()
                ->orderBy('sort_order')
                ->orderBy('bank_name')
                ->get()
        );
    }
}
