<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

/** Accounts seen in the workbook ledgers (Account/Method columns). Opening balances 0 (opening cash was 0). */
class BankingSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'DBBL', 'type' => 'bank', 'aliases' => ['dbbl', 'dutch-bangla', 'dutch bangla'], 'notes' => 'Dutch-Bangla Bank — main operating account'],
            ['name' => 'EBL', 'type' => 'bank', 'aliases' => ['ebl', 'eastern bank'], 'notes' => 'Eastern Bank'],
            ['name' => 'bKash', 'type' => 'wallet', 'aliases' => ['bkash'], 'notes' => 'Mobile wallet'],
            ['name' => 'Cash', 'type' => 'cash', 'aliases' => ['cash', 'petty cash'], 'notes' => 'Office cash'],
        ];
        foreach ($accounts as $i => $a) {
            BankAccount::query()->updateOrCreate(['name' => $a['name']], $a + ['sort' => $i + 1, 'opening_balance' => 0, 'opening_date' => '2026-03-31']);
        }
    }
}
