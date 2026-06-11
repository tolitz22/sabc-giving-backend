<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BankAccountController as AdminBankAccountController;
use App\Http\Controllers\Admin\DonationController as AdminDonationController;
use App\Http\Controllers\Admin\DonationStatsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\DonationBankTransferController;
use App\Http\Controllers\DonationCheckoutController;
use App\Http\Controllers\DonationProofUploadController;
use App\Http\Controllers\DonationStatusController;
use App\Http\Controllers\PaymongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/donations/bank-transfer/proof-upload', DonationProofUploadController::class)->middleware('throttle:20,1');
Route::post('/donations/bank-transfer', DonationBankTransferController::class)->middleware('throttle:20,1');
Route::post('/donations/checkout', DonationCheckoutController::class)->middleware('throttle:20,1');
Route::get('/donations/{donation:uuid}', DonationStatusController::class);
Route::get('/bank-accounts', BankAccountController::class);
Route::post('/webhooks/paymongo', PaymongoWebhookController::class);

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'active.admin', 'throttle:120,1'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::patch('/users/{user}/disable', [AdminUserController::class, 'disable']);
        Route::patch('/users/{user}/enable', [AdminUserController::class, 'enable']);
        Route::get('/bank-accounts', [AdminBankAccountController::class, 'index']);
        Route::post('/bank-accounts', [AdminBankAccountController::class, 'store']);
        Route::patch('/bank-accounts/{bankAccount}', [AdminBankAccountController::class, 'update']);
        Route::patch('/bank-accounts/{bankAccount}/enable', [AdminBankAccountController::class, 'enable']);
        Route::patch('/bank-accounts/{bankAccount}/disable', [AdminBankAccountController::class, 'disable']);
        Route::get('/donations', [AdminDonationController::class, 'index']);
        Route::get('/donations/{donation:uuid}', [AdminDonationController::class, 'show']);
        Route::patch('/donations/{donation:uuid}/verify', [AdminDonationController::class, 'verify']);
        Route::patch('/donations/{donation:uuid}/reject', [AdminDonationController::class, 'reject']);
        Route::delete('/donations/{donation:uuid}/proof', [AdminDonationController::class, 'deleteProof']);
        Route::get('/stats', DonationStatsController::class);
    });
});
