<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitLossExport implements FromArray, WithHeadings
{
    protected int $year;
    protected int $month;

    public function __construct(int $year, int $month)
    {
        $this->year = $year;
        $this->month = $month;
    }

    public function headings(): array
    {
        return ['Kategori', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit', 'Saldo Bersih'];
    }

    public function array(): array
    {
        $data = [];

        $results = DB::table('journal_entry_details')
            ->select('journal_entry_details.account_id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['REVENUE', 'EXPENSE'])
            ->whereYear('journal_entries.date', $this->year)
            ->whereMonth('journal_entries.date', $this->month)
            ->groupBy('journal_entry_details.account_id')
            ->orderBy('accounts.code')
            ->get();

        foreach ($results as $row) {
            // Profit & Loss Normal Balances: Revenue is Credit-normal, Expenses are Debit-normal.
            $saldo = $row->type === 'REVENUE'
                ? $row->total_credit - $row->total_debit
                : $row->total_debit - $row->total_credit;

            $data[] = [
                match($row->type) {
                    'REVENUE' => 'PENDAPATAN',
                    'EXPENSE' => 'BEBAN',
                    default => $row->type,
                },
                $row->code,
                $row->name,
                $row->total_debit,
                $row->total_credit,
                $saldo,
            ];
        }

        // =====================================================================
        // PERBAIKAN INTEGRITAS (PRIORITAS SEDANG):
        // Memastikan method pembantu di ProfitLossResource bersifat public sehingga
        // tidak memicu fatal error "Call to protected method" ketika mengekspor ke Excel.
        // =====================================================================
        $revenue = \App\Filament\Resources\ProfitLossResource::getTotalByType('REVENUE', $this->year, $this->month);
        $hpp = \App\Filament\Resources\ProfitLossResource::getTotalHpp($this->year, $this->month);
        $expense = \App\Filament\Resources\ProfitLossResource::getTotalExpense($this->year, $this->month);
        $profit = $revenue - $hpp - $expense;

        $data[] = ['', '', '', '', '', ''];
        $data[] = ['SUMMARY', '', '', '', '', ''];
        $data[] = ['Total Pendapatan', '', '', '', '', $revenue];
        $data[] = ['Harga Pokok Penjualan (HPP)', '', '', '', '', $hpp];
        $data[] = ['Beban Operasional', '', '', '', '', $expense];
        $data[] = ['PROFIT / LOSS', '', '', '', '', $profit];

        return $data;
    }
}
