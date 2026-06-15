<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Facades\DB;

class JournalService
{
    public static function createJournal(string $description, array $entries, ?string $referenceType = null, ?int $referenceId = null, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($description, $entries, $referenceType, $referenceId, $userId) {
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($entries as $entry) {
                if ($entry['type'] === 'DEBIT') {
                    $totalDebit += $entry['amount'];
                } else {
                    $totalCredit += $entry['amount'];
                }
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception("Journal not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}");
            }

            $journal = JournalEntry::create([
                'date' => now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_posted' => true,
                'created_by' => $userId,
            ]);

            foreach ($entries as $entry) {
                JournalEntryDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['type'] === 'DEBIT' ? $entry['amount'] : 0,
                    'credit' => $entry['type'] === 'CREDIT' ? $entry['amount'] : 0,
                    'description' => $entry['detail_description'] ?? $description,
                ]);
            }

            return $journal;
        });
    }

    public static function journalSalesInvoice(float $total, float $hpp, ?int $userId = null): void
    {
        $accountPiutang = Account::where('code', '1-10003')->first();
        $accountPenjualan = Account::where('code', '4-10001')->first();
        $accountHpp = Account::where('code', '5-10001')->first();
        $accountPersediaan = Account::where('code', '1-20001')->first();

        if (!$accountPiutang || !$accountPenjualan || !$accountHpp || !$accountPersediaan) {
            throw new \Exception('Required accounts for sales journal not found');
        }

        // Journal 1: Piutang vs Penjualan
        self::createJournal(
            'Penjualan barang dagang',
            [
                ['account_id' => $accountPiutang->id, 'type' => 'DEBIT', 'amount' => $total, 'detail_description' => 'Piutang customer'],
                ['account_id' => $accountPenjualan->id, 'type' => 'CREDIT', 'amount' => $total, 'detail_description' => 'Pendapatan penjualan'],
            ],
            null,
            null,
            $userId
        );

        // Journal 2: HPP vs Persediaan
        if ($hpp > 0) {
            self::createJournal(
                'Harga pokok penjualan',
                [
                    ['account_id' => $accountHpp->id, 'type' => 'DEBIT', 'amount' => $hpp, 'detail_description' => 'Beban HPP'],
                    ['account_id' => $accountPersediaan->id, 'type' => 'CREDIT', 'amount' => $hpp, 'detail_description' => 'Pengurangan persediaan'],
                ],
                null,
                null,
                $userId
            );
        }
    }

    public static function journalCashIn(float $amount, string $type, ?int $customerId = null, ?int $userId = null): void
    {
        $accountKas = Account::where('code', '1-10001')->first();
        $accountPiutang = Account::where('code', '1-10003')->first();
        $accountPendapatanLain = Account::where('code', '4-20001')->first();

        if (!$accountKas) {
            throw new \Exception('Cash account not found');
        }

        $entries = [
            ['account_id' => $accountKas->id, 'type' => 'DEBIT', 'amount' => $amount, 'detail_description' => 'Kas masuk'],
        ];

        if ($type === 'CUSTOMER_PAYMENT' && $accountPiutang) {
            $entries[] = ['account_id' => $accountPiutang->id, 'type' => 'CREDIT', 'amount' => $amount, 'detail_description' => 'Pelunasan piutang customer'];
        } else {
            $entries[] = ['account_id' => $accountPendapatanLain->id, 'type' => 'CREDIT', 'amount' => $amount, 'detail_description' => 'Pendapatan lain-lain'];
        }

        self::createJournal(
            $type === 'CUSTOMER_PAYMENT' ? 'Penerimaan pembayaran customer' : 'Pendapatan lain-lain',
            $entries,
            null,
            null,
            $userId
        );
    }

    public static function journalCashOut(float $amount, string $type, ?int $categoryAccountId = null, ?int $userId = null): void
    {
        $accountKas = Account::where('code', '1-10001')->first();

        if (!$accountKas) {
            throw new \Exception('Cash account not found');
        }

        $creditAccountId = $categoryAccountId ?? Account::where('code', '5-20001')->first()?->id;

        if (!$creditAccountId) {
            throw new \Exception('Expense account not found');
        }

        self::createJournal(
            'Pengeluaran kas: ' . $type,
            [
                ['account_id' => $creditAccountId, 'type' => 'DEBIT', 'amount' => $amount, 'detail_description' => 'Beban operasional'],
                ['account_id' => $accountKas->id, 'type' => 'CREDIT', 'amount' => $amount, 'detail_description' => 'Kas keluar'],
            ],
            null,
            null,
            $userId
        );
    }

    public static function journalGoodsReceipt(float $total, ?int $userId = null): void
    {
        $accountPersediaan = Account::where('code', '1-20001')->first();
        $accountHutang = Account::where('code', '2-10001')->first();

        if (!$accountPersediaan || !$accountHutang) {
            throw new \Exception('Required accounts for purchase journal not found');
        }

        self::createJournal(
            'Pembelian barang dagang',
            [
                ['account_id' => $accountPersediaan->id, 'type' => 'DEBIT', 'amount' => $total, 'detail_description' => 'Penambahan persediaan'],
                ['account_id' => $accountHutang->id, 'type' => 'CREDIT', 'amount' => $total, 'detail_description' => 'Hutang ke supplier'],
            ],
            null,
            null,
            $userId
        );
    }
}