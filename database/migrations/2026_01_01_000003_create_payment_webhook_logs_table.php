<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_type')->nullable()->index();
            $table->string('event_id')->nullable()->index();
            $table->json('payload');
            $table->text('signature')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
