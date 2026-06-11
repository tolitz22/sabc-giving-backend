<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'SABC Admin',
            'email' => env('ADMIN_EMAIL', 'admin@sabc.local'),
            'password' => env('ADMIN_PASSWORD', 'password'),
            'role' => 'admin',
        ]);

        Donation::factory()->count(8)->paid()->create();
        Donation::factory()->count(4)->bankTransferUnderReview()->create();
    }
}
