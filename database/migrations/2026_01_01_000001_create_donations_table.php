<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('donor_name');
            $table->string('donor_email');
            $table->string('donor_mobile')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('category');
            $table->string('giving_method');
            $table->text('note')->nullable();
            $table->string('status')->index();
            $table->string('gateway')->nullable();
            $table->string('gateway_checkout_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_reference')->nullable()->index();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['giving_method', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
