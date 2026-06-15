<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceSheetResource\Pages;
use App\Models\JournalEntryDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
        return auth()->user()->hasRole('Admin') 
            || auth()->user()->hasPermissionTo('view_balance_sheet')
            || auth()->user()->hasPermissionTo('view_financial_report');
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
                $year = request('tableFilters.periode.year', now()->year);
                $month = request('tableFilters.periode.month', now()->month);

                $asset = self::getTotalByType('ASSET', $year, $month);
                $liability = self::getTotalByType('LIABILITY', $year, $month);
                $equity = self::getTotalByType('EQUITY', $year, $month);
                $balance = $asset - ($liability + $equity);

                return new HtmlString('
                    <div class="fi-header grid gap-y-2">
                        <div class="flex items-center justify-between gap-x-4">
                            <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                                Laporan Neraca
                            </h1>
                            <span class="text-sm text-gray-500">Periode: ' . $month . '/' . $year . '</span>
                        </div>
                        <div class="grid grid-cols-4 gap-4 mt-4">
                            <div class="rounded-xl bg-green-50 p-4 border border-green-200">
                                <div class="text-sm text-green-600 font-medium">Total Aset</div>
                                <div class="text-xl font-bold text-green-700">Rp ' . number_format($asset, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-red-50 p-4 border border-red-200">
                                <div class="text-sm text-red-600 font-medium">Total Kewajiban</div>
                                <div class="text-xl font-bold text-red-700">Rp ' . number_format($liability, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 p-4 border border-blue-200">
                                <div class="text-sm text-blue-600 font-medium">Total Modal</div>
                                <div class="text-xl font-bold text-blue-700">Rp ' . number_format($equity, 0, ',', '.') . '</div>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 border border-gray-200">
                                <div class="text-sm text-gray-600 font-medium">Balance Check</div>
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
                    }),

                Tables\Columns\TextColumn::make('account.code')
                    ->label('Kode Akun')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Nama Akun')
                    ->searchable(),

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
                    ->label('Saldo')
                    ->state(function ($record): string {
                        $saldo = $record->account->normal_balance === 'DEBIT' 
                            ? $record->total_debit - $record->total_credit
                            : $record->total_credit - $record->total_debit;
                        return 'Rp ' . number_format($saldo, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('year')
                            ->label('Tahun')
                            ->options(array_combine(range(2024, 2030), range(2024, 2030)))
                            ->default(now()->year),
                        Forms\Components\Select::make('month')
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
                        'year' => request('tableFilters.periode.year', now()->year),
                        'month' => request('tableFilters.periode.month', now()->month),
                    ]))
                    ->openUrlInNewTab(),
            ])			
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $year = request('tableFilters.periode.year', now()->year);
        $month = request('tableFilters.periode.month', now()->month);

        return parent::getEloquentQuery()
            ->select('journal_entry_details.account_id')
            ->selectRaw('SUM(journal_entry_details.debit) as total_debit')
            ->selectRaw('SUM(journal_entry_details.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->whereIn('accounts.type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->whereYear('journal_entries.date', $year)
            ->whereMonth('journal_entries.date', $month)
            ->groupBy('journal_entry_details.account_id')
            ->with('account');
    }

    protected static function getTotalByType(string $type, int $year, int $month): float
    {
        $accounts = \DB::table('accounts')->where('type', $type)->get();
        $total = 0;

        foreach ($accounts as $account) {
            $result = \DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_id')
                ->where('journal_entry_details.account_id', $account->id)
                ->whereYear('journal_entries.date', $year)
                ->whereMonth('journal_entries.date', $month)
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceSheet::route('/'),
        ];
    }
}