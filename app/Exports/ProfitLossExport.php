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
        return ['Kategori', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit'];
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
            ];
        }

        // Summary
        $revenue = \App\Filament\Resources\ProfitLossResource::getTotalByType('REVENUE', $this->year, $this->month);
        $hpp = \App\Filament\Resources\ProfitLossResource::getTotalHpp($this->year, $this->month);
        $expense = \App\Filament\Resources\ProfitLossResource::getTotalExpense($this->year, $this->month);
        $profit = $revenue - $hpp - $expense;

        $data[] = ['', '', '', '', ''];
        $data[] = ['SUMMARY', '', '', '', ''];
        $data[] = ['Pendapatan', '', '', '', $revenue];
        $data[] = ['HPP', '', '', '', $hpp];
        $data[] = ['Beban', '', '', '', $expense];
        $data[] = ['PROFIT / LOSS', '', '', '', $profit];

        return $data;
    }
}