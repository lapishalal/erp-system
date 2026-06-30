<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfitLossResource\Pages;
use App\Models\Account;
use App\Models\JournalEntryDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

class ProfitLossResource extends Resource
{
    protected static ?string $model = JournalEntryDetail::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Profit & Loss';
    protected static ?string $modelLabel = 'Profit & Loss';
    protected static ?string $pluralModelLabel = 'Profit & Loss';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') 
            || auth()->check() && auth()->user()->hasPermissionTo('view_profit_loss')
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
                // Safely extract year and month from request or session
                $year = request()->input('tableFilters.periode.year') ?? now()->year;
                $month = request()->input('tableFilters.periode.month') ?? now()->month;

                $revenue = self::getTotalByType('REVENUE', $year, $month);
                $hpp = self::getTotalHpp($year, $month);
                $expense = self::getTotalExpense($year, $month);
                $profit = $revenue - $hpp - $expense;

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
                                Laporan Laba Rugi
                            </h1>
                            <span class="text-sm text-gray-500 font-medium bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700">
                                Periode: ' . $monthName . ' ' . $year . '
                            </span>
                        </div>
                        <div class="grid grid-cols-4 gap-4 mt-4">
                            <div class="rounded-xl bg-green-50 dark:bg-green-950/20 p-4 border border-green-200 dark:border-green-800">
                                <div class="text-sm text-green-600 dark:text-green-400 font-medium">Pendapatan</div>
                                <div class="text-xl font-bold text-green-700 dark:text-green-300">Rp ' . number_format($revenue, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 border border-red-200 dark:border-red-800">
                                <div class="text-sm text-red-600 dark:text-red-400 font-medium">HPP</div>
                                <div class="text-xl font-bold text-red-700 dark:text-red-300">Rp ' . number_format($hpp, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-orange-50 dark:bg-orange-950/20 p-4 border border-orange-200 dark:border-orange-800">
                                <div class="text-sm text-orange-600 dark:text-orange-400 font-medium">Beban Operasional</div>
                                <div class="text-xl font-bold text-orange-700 dark:text-orange-300">Rp ' . number_format($expense, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 dark:bg-blue-950/20 p-4 border border-blue-200 dark:border-blue-800">
                                <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Profit / Loss</div>
                                <div class="text-xl font-bold ' . ($profit >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-red-700 dark:text-red-300') . '">
                                    Rp ' . number_format($profit, 0, ',', '.') . '
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
                        'REVENUE' => 'PENDAPATAN',
                        'EXPENSE' => 'BEBAN',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'REVENUE' => 'success',
                        'EXPENSE' => 'danger',
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
                    ->label('Saldo Bersih')
                    ->state(function ($record): string {
                        // Profit & Loss Normal Balances:
                        // Revenue is Credit-normal. Expenses (including HPP) are Debit-normal.
                        $saldo = $record->account->type === 'REVENUE'
                            ? $record->total_credit - $record->total_debit
                            : $record->total_debit - $record->total_credit;
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
                        // Filters are applied directly in getEloquentQuery() to ensure aggregation queries are properly updated
                        return $query;
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn () => route('profit-loss.export', [
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
        // FIX: Mendukung pembacaan filter yang tangguh (robust) baik dari request form, query parameter, maupun default value
        $year = request()->input('tableFilters.periode.year') ?? now()->year;
        $month = request()->input('tableFilters.periode.month') ?? now()->month;

        return parent::getEloquentQuery()
            ->select('journal_entry_details.account_id')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['REVENUE', 'EXPENSE'])
            ->whereYear('journal_entries.date', $year)
            ->whereMonth('journal_entries.date', $month)
            ->groupBy('journal_entry_details.account_id')
            ->with('account');
    }

    // =====================================================================
    // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
    // Membuka visibilitas public agar Export class (ProfitLossExport) dapat memanggil fungsi-fungsi ini.
    // Menghapus hardcode kode akun '5-10001' untuk HPP dan menggunakan pattern '5-1%'
    // agar semua akun HPP/COGS terakumulasi dengan benar.
    // =====================================================================
    public static function getTotalByType(string $type, int $year, int $month): float
    {
        $result = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->where('accounts.type', $type)
            // HPP dipisahkan agar tidak dihitung dua kali
            ->where('accounts.code', 'not like', '5-1%') 
            ->whereYear('journal_entries.date', $year)
            ->whereMonth('journal_entries.date', $month)
            ->selectRaw('SUM(journal_entry_details.credit - journal_entry_details.debit) as total')
            ->first();

        return (float) ($result->total ?? 0);
    }

    public static function getTotalHpp(int $year, int $month): float
    {
        $result = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            // Menggunakan prefix '5-1%' agar mencakup semua sub-akun HPP/COGS
            ->where('accounts.code', 'like', '5-1%') 
            ->whereYear('journal_entries.date', $year)
            ->whereMonth('journal_entries.date', $month)
            ->selectRaw('SUM(journal_entry_details.debit - journal_entry_details.credit) as total')
            ->first();

        return (float) ($result->total ?? 0);
    }

    public static function getTotalExpense(int $year, int $month): float
    {
        $result = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->where('accounts.type', 'EXPENSE')
            // Mengecualikan HPP (5-1) agar hanya menghitung beban operasional murni (5-2, dll)
            ->where('accounts.code', 'not like', '5-1%') 
            ->whereYear('journal_entries.date', $year)
            ->whereMonth('journal_entries.date', $month)
            ->selectRaw('SUM(journal_entry_details.debit - journal_entry_details.credit) as total')
            ->first();

        return (float) ($result->total ?? 0);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfitLoss::route('/'),
        ];
    }
}
