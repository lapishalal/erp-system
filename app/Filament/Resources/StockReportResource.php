<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockReportResource\Pages;
use App\Models\StockTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockReportResource extends Resource
{
    protected static ?string $model = StockTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Stok';
    protected static ?string $modelLabel = 'Kartu Stok';
    protected static ?string $pluralModelLabel = 'Kartu Stok';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('view_stock_report') || auth()->check() && auth()->user()->hasPermissionTo('manage_inventory');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['product', 'warehouse', 'creator']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.code')
                    ->label('Kode Barang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                        'ADJUSTMENT' => 'warning',
                        'OPNAME' => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'IN' => 'Masuk',
                        'OUT' => 'Keluar',
                        'ADJUSTMENT' => 'Penyesuaian',
                        'OPNAME' => 'Stock Opname',
                    }),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (StockTransaction $record): string => 
                        $record->qty > 0 ? 'success' : 'danger'
                    ),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('remaining_stock')
                    ->label('Stok Akhir')
                    ->numeric()
                    ->alignEnd()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referensi')
                    ->state(function (StockTransaction $record): string {
                        $type = class_basename($record->reference_type ?? '');
                        return match ($type) {
                            'GoodsReceipt' => 'GR #' . $record->reference_id,
                            'DeliveryOrder' => 'SJ #' . $record->reference_id,
                            'StockOpname' => 'Opname #' . $record->reference_id,
                            default => $record->reference_type ? $type . ' #' . $record->reference_id : '-',
                        };
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(40)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')
                            ->label('Dari Tanggal')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('sampai')
                            ->label('Sampai Tanggal')
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Barang')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Transaksi')
                    ->options([
                        'IN' => 'Stok Masuk',
                        'OUT' => 'Stok Keluar',
                        'ADJUSTMENT' => 'Penyesuaian',
                        'OPNAME' => 'Stock Opname',
                    ]),
            ])
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filter')
                    ->icon('heroicon-o-funnel'),
            )
            ->headerActions([
                Tables\Actions\Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn () => route('stock-report.export', request()->only(['tableFilters'])))
                    ->openUrlInNewTab(),
            ])
            ->actions([])
            ->bulkActions([])
            ->paginated([25, 50, 100])
            ->poll(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockReports::route('/'),
        ];
    }
}