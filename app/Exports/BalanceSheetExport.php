<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Filament\Resources\BalanceSheetResource;
use Carbon\Carbon;

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
        $endDate = Carbon::create($this->year, $this->month)->endOfMonth()->toDateString();

        // =====================================================================
        // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
        // Neraca harus bersifat kumulatif (whereDate <= endDate) dari awal berdirinya perusahaan,
        // bukan hanya transaksi dalam bulan terpilih.
        // =====================================================================
        $results = DB::table('journal_entry_details')
            ->select('journal_entry_details.account_id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.normal_balance')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->whereDate('journal_entries.date', '<=', $endDate)
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

        // =====================================================================
        // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
        // Menambahkan Laba Rugi Berjalan (Current Earnings) ke dalam Ekuitas di Excel
        // agar ringkasan Neraca di Excel seimbang (Balance).
        // =====================================================================
        $currentEarnings = BalanceSheetResource::getCurrentEarnings($this->year, $this->month);
        $data[] = [
            'MODAL',
            '3-10003', // Kode akun virtual/sementara untuk Laba Tahun Berjalan
            'Laba Rugi Tahun Berjalan',
            0,
            0,
            $currentEarnings
        ];

        // Summary Cards
        $asset = BalanceSheetResource::getTotalByType('ASSET', $this->year, $this->month);
        $liability = BalanceSheetResource::getTotalByType('LIABILITY', $this->year, $this->month);
        $equity = BalanceSheetResource::getTotalByType('EQUITY', $this->year, $this->month);
        $totalEquityPlusEarnings = $equity + $currentEarnings;
        $balance = $asset - ($liability + $totalEquityPlusEarnings);

        $data[] = ['', '', '', '', '', ''];
        $data[] = ['RINGKASAN NERACA', '', '', '', '', ''];
        $data[] = ['Total Aset', '', '', '', '', $asset];
        $data[] = ['Total Kewajiban', '', '', '', '', $liability];
        $data[] = ['Total Modal (Sblm Laba Berjalan)', '', '', '', '', $equity];
        $data[] = ['Laba Rugi Tahun Berjalan', '', '', '', '', $currentEarnings];
        $data[] = ['Total Modal + Laba Berjalan', '', '', '', '', $totalEquityPlusEarnings];
        $data[] = [
            'Check Balance', 
            '', 
            '', 
            '', 
            '', 
            abs($balance) < 1 ? 'BALANCED' : 'SELISIH: ' . $balance
        ];

        return $data;
    }
}
