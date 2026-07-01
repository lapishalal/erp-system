<?php

namespace App\Filament\Pages;

use App\Exports\CustomerReportExport;
use App\Models\Customer;
use App\Models\SalesOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CustomerReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Customer';
    protected static ?string $title = 'Laporan Customer';
    protected static ?string $slug = 'customer-report';
    protected static string $view = 'filament.pages.customer-report';
    protected static ?int $sort = 22;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('view_reports')
        );
    }

    public function mount(): void
    {
        //
    }

    /**
     * Build the query with customer aggregates, applying date/status filters.
     */
    protected function buildQuery(): Builder
    {
        $tenantId = $this->getTenantId();

        // Read current filter state
        $periode = $this->tableFilters['periode'] ?? [];
        $dari = $periode['dari'] ?? null;
        $sampai = $periode['sampai'] ?? null;
        $status = $this->tableFilters['status']['value'] ?? null;

        // Default: current month (only when user hasn't interacted with filters yet)
        if ($this->tableFilters === null) {
            $dari = now()->startOfMonth()->toDateString();
            $sampai = now()->endOfMonth()->toDateString();
        }

        $subquery = DB::table('sales_orders')
            ->select('customer_id')
            ->selectRaw('SUM(total_amount) as total_omset')
            ->selectRaw('SUM(total_cost) as total_hpp')
            ->selectRaw('SUM(profit) as total_profit')
            ->selectRaw('COUNT(*) as total_orders')
            ->where('status', '!=', 'CANCEL')
            ->where('tenant_id', $tenantId)
            ->when($dari, fn ($q) => $q->whereDate('date', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('date', '<=', $sampai))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->groupBy('customer_id');

        return Customer::query()
            ->select('customers.*')
            ->selectRaw('COALESCE(agg.total_omset, 0) as total_omset')
            ->selectRaw('COALESCE(agg.total_hpp, 0) as total_hpp')
            ->selectRaw('COALESCE(agg.total_profit, 0) as total_profit')
            ->selectRaw('COALESCE(agg.total_orders, 0) as total_orders')
            ->leftJoinSub($subquery, 'agg', 'customers.id', '=', 'agg.customer_id')
            ->where('customers.tenant_id', $tenantId);
    }

    public function getTableQuery(): Builder
    {
        return $this->buildQuery();
    }

    private function getTenantId(): ?string
    {
        return auth()->user()->tenant_id ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('total_orders')
                    ->label('Jumlah Order')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_omset')
                    ->label('Total Omset')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Omset'),
                    ]),

                TextColumn::make('total_hpp')
                    ->label('Total HPP')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total HPP'),
                    ]),

                TextColumn::make('total_profit')
                    ->label('Profit')
                    ->money('IDR')
                    ->alignEnd()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Profit'),
                    ]),

                TextColumn::make('margin')
                    ->label('Margin %')
                    ->state(function (Customer $record): string {
                        $omset = (float) $record->total_omset;
                        $profit = (float) $record->total_profit;
                        if ($omset > 0) {
                            return number_format(($profit / $omset) * 100, 1) . '%';
                        }
                        return '0%';
                    })
                    ->alignEnd()
                    ->color('info'),
            ])
            ->filters([
                Filter::make('periode')
                    ->label('Periode')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal')
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data) {
                        // Filter logic is handled in buildQuery() via tableFilters state
                        return $query;
                    }),

                SelectFilter::make('status')
                    ->label('Status Order')
                    ->options([
                        'OPEN' => 'Open',
                        'PARTIAL' => 'Partial',
                        'COMPLETE' => 'Complete',
                    ])
                    ->query(function (Builder $query, array $data) {
                        // Filter logic is handled in buildQuery() via tableFilters state
                        return $query;
                    }),
            ])
            ->actions([
                Action::make('viewOrders')
                    ->label('Lihat Order')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (Customer $record) => 'Orderan - ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (Customer $record) {
                        $periode = $this->tableFilters['periode'] ?? [];
                        $dari = $periode['dari'] ?? null;
                        $sampai = $periode['sampai'] ?? null;

                        $soQuery = SalesOrder::query()
                            ->where('customer_id', $record->id)
                            ->where('status', '!=', 'CANCEL')
                            ->when($dari, fn ($q) => $q->whereDate('date', '>=', $dari))
                            ->when($sampai, fn ($q) => $q->whereDate('date', '<=', $sampai))
                            ->orderByDesc('date')
                            ->get(['so_number', 'date', 'status', 'total_amount', 'total_cost', 'profit']);

                        return view('filament.pages.customer-report-orders', [
                            'orders' => $soQuery,
                        ]);
                    }),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        $periode = $this->tableFilters['periode'] ?? [];
                        $dari = $periode['dari'] ?? null;
                        $sampai = $periode['sampai'] ?? null;
                        $status = $this->tableFilters['status']['value'] ?? null;

                        return Excel::download(
                            new CustomerReportExport($dari, $sampai, $status),
                            'customer-report-' . now()->format('Ymd_His') . '.xlsx'
                        );
                    }),
            ])
            ->defaultSort('total_omset', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Tidak ada data customer')
            ->emptyStateDescription('Tidak ada transaksi penjualan untuk periode yang dipilih.');
    }
}
