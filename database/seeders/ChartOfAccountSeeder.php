<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ASET (1)
            ['code' => '1-10000', 'name' => 'Aset Lancar', 'type' => 'ASSET', 'level' => 1, 'normal_balance' => 'DEBIT', 'parent_id' => null],
            ['code' => '1-10001', 'name' => 'Kas', 'type' => 'ASSET', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 1],
            ['code' => '1-10002', 'name' => 'Bank', 'type' => 'ASSET', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 1],
            ['code' => '1-10003', 'name' => 'Piutang Usaha', 'type' => 'ASSET', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 1],
            ['code' => '1-20000', 'name' => 'Persediaan', 'type' => 'ASSET', 'level' => 1, 'normal_balance' => 'DEBIT', 'parent_id' => null],
            ['code' => '1-20001', 'name' => 'Persediaan Barang Dagang', 'type' => 'ASSET', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 5],

            // KEWAJIBAN (2)
            ['code' => '2-10000', 'name' => 'Kewajiban Lancar', 'type' => 'LIABILITY', 'level' => 1, 'normal_balance' => 'CREDIT', 'parent_id' => null],
            ['code' => '2-10001', 'name' => 'Hutang Usaha', 'type' => 'LIABILITY', 'level' => 2, 'normal_balance' => 'CREDIT', 'parent_id' => 7],
            ['code' => '2-20001', 'name' => 'Hutang PPN', 'type' => 'LIABILITY', 'level' => 2, 'normal_balance' => 'CREDIT', 'parent_id' => 7],

            // MODAL (3)
            ['code' => '3-10001', 'name' => 'Modal Pemilik', 'type' => 'EQUITY', 'level' => 1, 'normal_balance' => 'CREDIT', 'parent_id' => null],
            ['code' => '3-10002', 'name' => 'Laba Ditahan', 'type' => 'EQUITY', 'level' => 1, 'normal_balance' => 'CREDIT', 'parent_id' => null],

            // PENDAPATAN (4)
            ['code' => '4-10000', 'name' => 'Pendapatan Usaha', 'type' => 'REVENUE', 'level' => 1, 'normal_balance' => 'CREDIT', 'parent_id' => null],
            ['code' => '4-10001', 'name' => 'Penjualan', 'type' => 'REVENUE', 'level' => 2, 'normal_balance' => 'CREDIT', 'parent_id' => 12],
            ['code' => '4-20001', 'name' => 'Pendapatan Lain-lain', 'type' => 'REVENUE', 'level' => 2, 'normal_balance' => 'CREDIT', 'parent_id' => 12],

            // BEBAN (5)
            ['code' => '5-10000', 'name' => 'Harga Pokok Penjualan', 'type' => 'EXPENSE', 'level' => 1, 'normal_balance' => 'DEBIT', 'parent_id' => null],
            ['code' => '5-10001', 'name' => 'HPP', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 15],
            ['code' => '5-20000', 'name' => 'Beban Operasional', 'type' => 'EXPENSE', 'level' => 1, 'normal_balance' => 'DEBIT', 'parent_id' => null],
            ['code' => '5-20001', 'name' => 'Beban Gaji', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20002', 'name' => 'Beban Transport', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20003', 'name' => 'Beban Marketing', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20004', 'name' => 'Beban Listrik & Air', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20005', 'name' => 'Beban Internet', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20006', 'name' => 'Beban Sewa', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
            ['code' => '5-20007', 'name' => 'Beban Pajak', 'type' => 'EXPENSE', 'level' => 2, 'normal_balance' => 'DEBIT', 'parent_id' => 17],
        ];

        foreach ($accounts as $index => $account) {
            Account::create([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'parent_id' => $account['parent_id'],
                'level' => $account['level'],
                'normal_balance' => $account['normal_balance'],
            ]);
        }
    }
}