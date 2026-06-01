<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['bank_name' => 'BDO Unibank', 'account_number' => '001234567890', 'account_name' => 'Sterling Insurance Company Inc.', 'branch' => 'Makati Main Branch'],
            ['bank_name' => 'Metrobank', 'account_number' => '123456789012', 'account_name' => 'Sterling Insurance Company Inc.', 'branch' => 'BGC Branch'],
            ['bank_name' => 'BPI', 'account_number' => '9876543210', 'account_name' => 'Sterling Insurance Company Inc.', 'branch' => 'Ortigas Branch'],
            ['bank_name' => 'UnionBank', 'account_number' => '1122334455', 'account_name' => 'Sterling Insurance Company Inc.', 'branch' => 'Online'],
        ];

        foreach ($accounts as $account) {
            BankAccount::updateOrCreate(
                ['account_number' => $account['account_number']],
                $account,
            );
        }
    }
}
