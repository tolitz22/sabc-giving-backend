<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('role');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('rejected_by')->nullable()->after('rejected_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->foreignId('proof_deleted_by')->nullable()->after('proof_expires_at')->constrained('users')->nullOnDelete();
            $table->timestamp('proof_deleted_at')->nullable()->after('proof_deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proof_deleted_by');
            $table->dropColumn('proof_deleted_at');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn('rejected_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
