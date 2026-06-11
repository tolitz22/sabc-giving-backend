<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@sabc.local')],
            [
                'name' => 'SABC Admin',
                'password' => env('ADMIN_PASSWORD', 'change-me'),
                'role' => User::ROLE_SUPER_ADMIN,
            ],
        );
    }
}
