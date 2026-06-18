<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosTransactionResource\Pages;
use App\Models\PosTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosTransactionResource extends Resource
{
    protected static ?string $model = PosTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Riwayat POS';
    protected static ?string $modelLabel = 'Transaksi POS';
    protected static ?string $pluralModelLabel = 'Riwayat Transaksi POS';

    public static function shouldRegisterNavigation(): bool
	{
		return auth()->check() && auth()->user()->hasRole('Admin') 
			|| auth()->check() && auth()->user()->hasPermissionTo('manage_pos')
			|| auth()->check() && auth()->user()->hasPermissionTo('manage_sales_orders');
	}

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['details.product', 'customer', 'creator']))
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kasir')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Walk-in'),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qty')
                    ->numeric(),
                Tables\Columns\TextColumn::make('subtotal')
                    ->money('IDR')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('discount')
                    ->money('IDR')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total')
                    ->money('IDR')
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('IDR')
                    ->label('Bayar')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('change_amount')
                    ->money('IDR')
                    ->label('Kembalian')
                    ->alignEnd()
                    ->color('success'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'CASH' => 'Tunai',
                        'DEBIT' => 'Debit',
                        'QRIS' => 'QRIS',
                        'TRANSFER' => 'Transfer',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('details')
                    ->label('Barang')
                    ->formatStateUsing(function ($record) {
                        return $record->details->map(fn ($d) => $d->product->name . ' (' . $d->qty . ')')->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->details->map(fn ($d) => $d->product->name . ' x' . $d->qty . ' @ Rp ' . number_format($d->price, 0, ',', '.'))->join("\n");
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari Tanggal')->default(now()->startOfDay()),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai Tanggal')->default(now()->endOfDay()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['sampai'], fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Bayar')
                    ->options([
                        'CASH' => 'Tunai',
                        'DEBIT' => 'Debit',
                        'QRIS' => 'QRIS',
                        'TRANSFER' => 'Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('created_by')
                    ->label('Kasir')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
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
                    ->url(fn () => route('pos-report.export', request()->only(['tableFilters'])))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print')
                        ->label('Print Struk')
                        ->icon('heroicon-o-printer')
                        ->url(fn (PosTransaction $record): string => route('pos.print', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([25, 50, 100])
            ->poll(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosTransactions::route('/'),
            'view' => Pages\ViewPosTransaction::route('/{record}'),
        ];
    }
}