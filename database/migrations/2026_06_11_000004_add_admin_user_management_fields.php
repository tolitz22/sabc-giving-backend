<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('last_login_at')->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable()->after('created_by');
        });

        User::where('role', 'admin')->update(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('disabled_at');
        });
    }
};
