<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceSheetResource\Pages;
use App\Models\JournalEntryDetail;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BalanceSheetResource extends Resource
{
    protected static ?string $model = JournalEntryDetail::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Neraca';
    protected static ?string $modelLabel = 'Neraca';
    protected static ?string $pluralModelLabel = 'Neraca';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') 
            || auth()->check() && auth()->user()->hasPermissionTo('view_balance_sheet')
            || auth()->check() && auth()->user()->hasPermissionTo('view_financial_report');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->header(function () {
                $year = request()->input('tableFilters.periode.year') ?? now()->year;
                $month = request()->input('tableFilters.periode.month') ?? now()->month;

                $asset = self::getTotalByType('ASSET', $year, $month);
                $liability = self::getTotalByType('LIABILITY', $year, $month);
                $equity = self::getTotalByType('EQUITY', $year, $month);
                
                // =====================================================================
                // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
                // Laba Ditahan / Retained Earnings berjalan (Current Year Earnings) harus dimasukkan
                // ke dalam komponen Modal (Equity) agar Neraca seimbang (Balance).
                // Formula: Total Aset = Total Kewajiban + Total Modal + Laba Rugi Berjalan
                // =====================================================================
                $currentEarnings = self::getCurrentEarnings($year, $month);
                $totalEquityPlusEarnings = $equity + $currentEarnings;
                $balance = $asset - ($liability + $totalEquityPlusEarnings);

                $monthNames = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ];
                $monthName = $monthNames[(int)$month] ?? $month;

                return new HtmlString('
                    <div class="fi-header grid gap-y-2">
                        <div class="flex items-center justify-between gap-x-4">
                            <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                                Laporan Neraca
                            </h1>
                            <span class="text-sm text-gray-500 font-medium bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700">
                                Posisi Per: Akhir ' . $monthName . ' ' . $year . '
                            </span>
                        </div>
                        <div class="grid grid-cols-5 gap-4 mt-4">
                            <div class="rounded-xl bg-green-50 dark:bg-green-950/20 p-4 border border-green-200 dark:border-green-800">
                                <div class="text-sm text-green-600 dark:text-green-400 font-medium">Total Aset</div>
                                <div class="text-xl font-bold text-green-700 dark:text-green-300">Rp ' . number_format($asset, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 border border-red-200 dark:border-red-800">
                                <div class="text-sm text-red-600 dark:text-red-400 font-medium">Total Kewajiban</div>
                                <div class="text-xl font-bold text-red-700 dark:text-red-300">Rp ' . number_format($liability, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-indigo-50 dark:bg-indigo-950/20 p-4 border border-indigo-200 dark:border-indigo-800">
                                <div class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">Laba Rugi Berjalan</div>
                                <div class="text-xl font-bold text-indigo-700 dark:text-indigo-300">Rp ' . number_format($currentEarnings, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 dark:bg-blue-950/20 p-4 border border-blue-200 dark:border-blue-800">
                                <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Total Modal + LR</div>
                                <div class="text-xl font-bold text-blue-700 dark:text-blue-300">Rp ' . number_format($totalEquityPlusEarnings, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-900/40 p-4 border border-gray-200 dark:border-gray-800">
                                <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">Balance Check</div>
                                <div class="text-xl font-bold ' . (abs($balance) < 1 ? 'text-green-700' : 'text-red-700') . '">
                                    ' . (abs($balance) < 1 ? '✅ Balance' : '❌ Selisih Rp ' . number_format(abs($balance), 0, ',', '.')) . '
                                </div>
                            </div>
                        </div>
                    </div>
                ');
            })
            ->columns([
                Tables\Columns\TextColumn::make('account.type')
                    ->label('Kategori')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ASSET' => 'ASET',
                        'LIABILITY' => 'KEWAJIBAN',
                        'EQUITY' => 'MODAL',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ASSET' => 'success',
                        'LIABILITY' => 'danger',
                        'EQUITY' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('account.code')
                    ->label('Kode Akun')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Debit')
                    ->state(fn ($record) => $record->total_debit)
                    ->money('IDR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_credit')
                    ->label('Kredit')
                    ->state(fn ($record) => $record->total_credit)
                    ->money('IDR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo Kumulatif')
                    ->state(function ($record): string {
                        $saldo = $record->account->normal_balance === 'DEBIT' 
                            ? $record->total_debit - $record->total_credit
                            : $record->total_credit - $record->total_debit;
                        return 'Rp ' . number_format($saldo, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components::Select::make('year')
                            ->label('Tahun')
                            ->options(array_combine(range(2024, 2030), range(2024, 2030)))
                            ->default(now()->year),
                        Forms\Components::Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ])
                            ->default(now()->month),
                    ])
                    ->query(function ($query, array $data) {
                        return $query;
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn () => route('balance-sheet.export', [
                        'year' => request()->input('tableFilters.periode.year') ?? now()->year,
                        'month' => request()->input('tableFilters.periode.month') ?? now()->month,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $year = request()->input('tableFilters.periode.year') ?? now()->year;
        $month = request()->input('tableFilters.periode.month') ?? now()->month;

        // Mendapatkan tanggal batas akhir bulan terpilih (end of month)
        $endDate = Carbon::create($year, $month)->endOfMonth()->toDateString();

        // =====================================================================
        // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
        // Neraca adalah akumulasi transaksi dari awal berdirinya perusahaan hingga tanggal pelaporan.
        // Diubah dari filter 'whereYear' dan 'whereMonth' menjadi 'whereDate <= endDate'.
        // Menghindari N+1 query dengan eager loading 'account'.
        // =====================================================================
        return parent::getEloquentQuery()
            ->select('journal_entry_details.account_id')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->whereDate('journal_entries.date', '<=', $endDate)
            ->groupBy('journal_entry_details.account_id')
            ->with('account');
    }

    // =====================================================================
    // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
    // Visibilitas method diubah ke public agar BalanceSheetExport bisa memanggilnya secara langsung.
    // Filter diubah dari bulanan menjadi kumulatif (whereDate <= endDate).
    // =====================================================================
    public static function getTotalByType(string $type, int $year, int $month): float
    {
        $endDate = Carbon::create($year, $month)->endOfMonth()->toDateString();
        $accounts = DB::table('accounts')->where('type', $type)->get();
        $total = 0;

        foreach ($accounts as $account) {
            $result = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
                ->where('journal_entry_details.account_id', $account->id)
                ->whereDate('journal_entries.date', '<=', $endDate)
                ->selectRaw('SUM(journal_entry_details.debit) as total_debit, SUM(journal_entry_details.credit) as total_credit')
                ->first();

            $debit = (float) ($result->total_debit ?? 0);
            $credit = (float) ($result->total_credit ?? 0);

            if ($account->normal_balance === 'DEBIT') {
                $total += ($debit - $credit);
            } else {
                $total += ($credit - $debit);
            }
        }

        return $total;
    }

    /**
     * Menghitung Laba/Rugi berjalan (Current Year Earnings) secara kumulatif 
     * hingga bulan pelaporan, karena Laba Rugi mempengaruhi nilai ekuitas di Neraca.
     */
    public static function getCurrentEarnings(int $year, int $month): float
    {
        $endDate = Carbon::create($year, $month)->endOfMonth()->toDateString();

        // 1. Pendapatan (Revenue)
        $revenueResult = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->where('accounts.type', 'REVENUE')
            ->whereDate('journal_entries.date', '<=', $endDate)
            ->selectRaw('SUM(journal_entry_details.credit - journal_entry_details.debit) as total')
            ->first();
        $revenue = (float) ($revenueResult->total ?? 0);

        // 2. HPP (COGS) - Code 5-1%
        $hppResult = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->where('accounts.code', 'like', '5-1%')
            ->whereDate('journal_entries.date', '<=', $endDate)
            ->selectRaw('SUM(journal_entry_details.debit - journal_entry_details.credit) as total')
            ->first();
        $hpp = (float) ($hppResult->total ?? 0);

        // 3. Beban Operasional (Expenses) - Type EXPENSE tapi bukan HPP
        $expenseResult = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->where('accounts.type', 'EXPENSE')
            ->where('accounts.code', 'not like', '5-1%')
            ->whereDate('journal_entries.date', '<=', $endDate)
            ->selectRaw('SUM(journal_entry_details.debit - journal_entry_details.credit) as total')
            ->first();
        $expense = (float) ($expenseResult->total ?? 0);

        return $revenue - $hpp - $expense;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceSheet::route('/'),
        ];
    }
}
