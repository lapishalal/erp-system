<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class CashFlowExport implements FromArray, WithHeadings
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
        return ['Kategori/Jenis', 'Tanggal', 'Keterangan/Customer', 'Pemasukan (Debit)', 'Pengeluaran (Kredit)'];
    }

    public function array(): array
    {
        $data = [];

        // 1. Ambil Saldo Awal Kas & Bank (Kumulatif transaksi sebelum awal bulan terpilih)
        $startOfMonth = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        
        $prevCashIn = DB::table('cash_in')
            ->whereDate('date', '<', $startOfMonth)
            ->sum('amount');
            
        $prevCashOut = DB::table('cash_out')
            ->whereDate('date', '<', $startOfMonth)
            ->sum('amount');
            
        $saldoAwal = (float)($prevCashIn - $prevCashOut);

        $data[] = ['SALDO AWAL KAS & BANK', '', 'Saldo dari akumulasi periode sebelumnya', $saldoAwal, ''];
        $data[] = ['', '', '', '', ''];

        // 2. Ambil Kas Masuk Bulan Berjalan
        $data[] = ['KAS MASUK (RECEIPTS)', '', '', '', ''];
        
        $cashIns = DB::table('cash_in')
            ->leftJoin('customers', 'customers.id', '=', 'cash_in.customer_id')
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month)
            ->select('cash_in.*', 'customers.name as customer_name')
            ->orderBy('date', 'asc')
            ->get();

        $totalCashIn = 0;
        foreach ($cashIns as $ci) {
            $amt = (float)$ci->amount;
            $totalCashIn += $amt;
            $data[] = [
                $ci->type === 'CUSTOMER_PAYMENT' ? 'Penerimaan Piutang' : 'Pendapatan Lain',
                Carbon::parse($ci->date)->format('d/m/Y'),
                ($ci->customer_name ? $ci->customer_name . ' - ' : '') . ($ci->description ?? '-'),
                $amt,
                ''
            ];
        }
        $data[] = ['Total Kas Masuk', '', '', $totalCashIn, ''];
        $data[] = ['', '', '', '', ''];

        // 3. Ambil Kas Keluar Bulan Berjalan
        $data[] = ['KAS KELUAR (DISBURSEMENTS)', '', '', '', ''];
        
        $cashOuts = DB::table('cash_out')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'cash_out.category_id')
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month)
            ->select('cash_out.*', 'expense_categories.name as category_name')
            ->orderBy('date', 'asc')
            ->get();

        $totalCashOut = 0;
        foreach ($cashOuts as $co) {
            $amt = (float)$co->amount;
            $totalCashOut += $amt;
            $data[] = [
                'Beban: ' . ($co->category_name ?? $co->type),
                Carbon::parse($co->date)->format('d/m/Y'),
                $co->description ?? '-',
                '',
                $amt
            ];
        }
        $data[] = ['Total Pengeluaran', '', '', '', $totalCashOut];
        $data[] = ['', '', '', '', ''];

        // 4. Ringkasan Akhir
        $netCashFlow = $totalCashIn - $totalCashOut;
        $saldoAkhir = $saldoAwal + $netCashFlow;

        $data[] = ['RINGKASAN CASH FLOW', '', '', '', ''];
        $data[] = ['Net Cash Flow Bulan Ini', '', '', $netCashFlow >= 0 ? $netCashFlow : '', $netCashFlow < 0 ? abs($netCashFlow) : ''];
        $data[] = ['SALDO AKHIR KAS & BANK', '', 'Saldo kas tersedia per akhir periode', $saldoAkhir, ''];

        return $data;
    }
}
