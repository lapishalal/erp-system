<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BalanceSheetExport implements FromArray, WithHeadings
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
        return ['Kategori', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit', 'Saldo'];
    }

    public function array(): array
    {
        $data = [];

        $results = DB::table('journal_entry_details')
            ->select('journal_entry_details.account_id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.normal_balance')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->whereYear('journal_entries.date', $this->year)
            ->whereMonth('journal_entries.date', $this->month)
            ->groupBy('journal_entry_details.account_id')
            ->orderBy('accounts.code')
            ->get();

        foreach ($results as $row) {
            $saldo = $row->normal_balance === 'DEBIT' 
                ? $row->total_debit - $row->total_credit
                : $row->total_credit - $row->total_debit;

            $data[] = [
                match($row->type) {
                    'ASSET' => 'ASET',
                    'LIABILITY' => 'KEWAJIBAN',
                    'EQUITY' => 'MODAL',
                    default => $row->type,
                },
                $row->code,
                $row->name,
                $row->total_debit,
                $row->total_credit,
                $saldo,
            ];
        }

        return $data;
    }
}