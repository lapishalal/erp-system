<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\JournalEntryDetail;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LedgerReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';
    protected static string $view = 'filament.pages.ledger-report';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'account_id' => null,
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('account_id')
                    ->label('Pilih Akun')
                    ->options(Account::where('is_active', true)->orderBy('code')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                DatePicker::make('from_date')
                    ->label('Dari Tanggal')
                    ->required()
                    ->default(now()->startOfMonth()),

                DatePicker::make('to_date')
                    ->label('Sampai Tanggal')
                    ->required()
                    ->default(now()->endOfMonth()),
            ])
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $accountId = $this->data['account_id'] ?? null;
        $fromDate = $this->data['from_date'] ?? null;
        $toDate = $this->data['to_date'] ?? null;

        if (!$accountId || !$fromDate || !$toDate) {
            return [
                'account' => null,
                'saldo_awal' => 0,
                'transactions' => [],
                'total_debit' => 0,
                'total_kredit' => 0,
                'saldo_akhir' => 0,
            ];
        }

        $account = Account::find($accountId);

        // Hitung saldo awal (sebelum from_date)
        $saldoAwal = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->where('jed.account_id', $accountId)
            ->where('je.date', '<', $fromDate)
            ->where('je.is_posted', true)
            ->selectRaw('COALESCE(SUM(jed.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(jed.credit), 0) as total_kredit')
            ->first();

        $saldoAwal = match ($account->normal_balance) {
            'DEBIT' => ($saldoAwal->total_debit ?? 0) - ($saldoAwal->total_kredit ?? 0),
            'CREDIT' => ($saldoAwal->total_kredit ?? 0) - ($saldoAwal->total_debit ?? 0),
            default => ($saldoAwal->total_debit ?? 0) - ($saldoAwal->total_kredit ?? 0),
        };

        // Ambil transaksi dalam periode
        $transactions = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_id')
            ->where('jed.account_id', $accountId)
            ->whereBetween('je.date', [$fromDate, $toDate])
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

        $runningSaldo = $saldoAwal;
        $totalDebit = 0;
        $totalKredit = 0;

        foreach ($transactions as $t) {
            $totalDebit += $t->debit;
            $totalKredit += $t->credit;

            if ($account->normal_balance === 'DEBIT') {
                $runningSaldo += $t->debit - $t->credit;
            } else {
                $runningSaldo += $t->credit - $t->debit;
            }

            $t->saldo = $runningSaldo;
        }

        // Hitung saldo akhir
        $saldoAkhir = match ($account->normal_balance) {
            'DEBIT' => $saldoAwal + $totalDebit - $totalKredit,
            'CREDIT' => $saldoAwal + $totalKredit - $totalDebit,
            default => $saldoAwal + $totalDebit - $totalKredit,
        };

        return [
            'account' => $account,
            'saldo_awal' => $saldoAwal,
            'transactions' => $transactions,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'saldo_akhir' => $saldoAkhir,
        ];
    }
}