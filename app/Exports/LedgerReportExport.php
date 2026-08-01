<?php

namespace App\Exports;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class LedgerReportExport implements FromArray, WithHeadings
{
    protected int $accountId;
    protected string $from;
    protected string $to;

    public function __construct(int $accountId, string $from, string $to)
    {
        $this->accountId = $accountId;
        $this->from = $from;
        $this->to = $to;
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
    }

    public function array(): array
    {
        $account = Account::find($this->accountId);
        if (!$account) {
            return [];
        }

        $saldoAwal = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->where('jed.account_id', $this->accountId)
            ->where('je.date', '<', $this->from)
            ->where('je.is_posted', true)
            ->selectRaw('COALESCE(SUM(jed.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(jed.credit), 0) as total_kredit')
            ->first();

        $saldoAwal = match ($account->normal_balance) {
            'CREDIT' => ($saldoAwal->total_kredit ?? 0) - ($saldoAwal->total_debit ?? 0),
            default => ($saldoAwal->total_debit ?? 0) - ($saldoAwal->total_kredit ?? 0),
        };

        $transactions = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->where('jed.account_id', $this->accountId)
            ->whereBetween('je.date', [$this->from, $this->to])
            ->where('je.is_posted', true)
            ->select(
                'je.date',
                'je.id as journal_id',
                'je.description',
                'jed.debit',
                'jed.credit',
                'jed.description as detail_description'
            )
            ->orderBy('je.date')
            ->orderBy('je.id')
            ->get();

        $running = (float) $saldoAwal;
        $data = [];
        $data[] = [
            'SALDO AWAL',
            '',
            'Saldo ' . $account->code . ' - ' . $account->name,
            '',
            '',
            number_format($running, 2, ',', '.'),
        ];

        $totalDebit = 0;
        $totalKredit = 0;

        foreach ($transactions as $t) {
            $totalDebit += (float) $t->debit;
            $totalKredit += (float) $t->credit;

            if ($account->normal_balance === 'CREDIT') {
                $running += (float) $t->credit - (float) $t->debit;
            } else {
                $running += (float) $t->debit - (float) $t->credit;
            }

            $desc = $t->description;
            if ($t->detail_description && $t->detail_description !== $t->description) {
                $desc .= ' | ' . $t->detail_description;
            }

            $data[] = [
                Carbon::parse($t->date)->format('d/m/Y'),
                'JU-' . str_pad((string) $t->journal_id, 5, '0', STR_PAD_LEFT),
                $desc,
                number_format((float) $t->debit, 2, ',', '.'),
                number_format((float) $t->credit, 2, ',', '.'),
                number_format($running, 2, ',', '.'),
            ];
        }

        $data[] = ['TOTAL', '', '', number_format($totalDebit, 2, ',', '.'), number_format($totalKredit, 2, ',', '.'), ''];
        $data[] = ['SALDO AKHIR', '', '', '', '', number_format($running, 2, ',', '.')];

        return $data;
    }
}
