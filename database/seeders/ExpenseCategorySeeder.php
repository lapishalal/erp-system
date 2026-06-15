<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Gaji', 'account_code' => '5-20001'],
            ['name' => 'Transport', 'account_code' => '5-20002'],
            ['name' => 'Marketing', 'account_code' => '5-20003'],
            ['name' => 'Listrik & Air', 'account_code' => '5-20004'],
            ['name' => 'Internet', 'account_code' => '5-20005'],
            ['name' => 'Sewa', 'account_code' => '5-20006'],
            ['name' => 'Pajak', 'account_code' => '5-20007'],
        ];

        foreach ($categories as $cat) {
            $account = Account::where('code', $cat['account_code'])->first();
            if ($account) {
                ExpenseCategory::create([
                    'name' => $cat['name'],
                    'account_id' => $account->id,
                ]);
            }
        }
    }
}