<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['bank_name' => 'BPI', 'account_number' => '0123 4567 8901', 'sort_order' => 10],
            ['bank_name' => 'BDO', 'account_number' => '0045 6789 0123', 'sort_order' => 20],
            ['bank_name' => 'Metrobank', 'account_number' => '7890 1234 5678', 'sort_order' => 30],
            ['bank_name' => 'UnionBank', 'account_number' => '1098 7654 3210', 'sort_order' => 40],
        ];

        foreach ($accounts as $account) {
            BankAccount::updateOrCreate(
                ['bank_name' => $account['bank_name']],
                [
                    'account_name' => 'Scripture Alone Baptist Church',
                    'account_number' => $account['account_number'],
                    'instructions' => 'Please use your name as reference when making a transfer.',
                    'is_enabled' => true,
                    'sort_order' => $account['sort_order'],
                ],
            );
        }
    }
}
