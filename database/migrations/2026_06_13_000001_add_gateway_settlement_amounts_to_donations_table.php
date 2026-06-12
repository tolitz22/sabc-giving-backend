<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->decimal('gateway_fee_amount', 12, 2)->nullable()->after('gateway_reference');
            $table->decimal('gateway_tax_amount', 12, 2)->nullable()->after('gateway_fee_amount');
            $table->decimal('gateway_net_amount', 12, 2)->nullable()->after('gateway_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_fee_amount',
                'gateway_tax_amount',
                'gateway_net_amount',
            ]);
        });
    }
};
