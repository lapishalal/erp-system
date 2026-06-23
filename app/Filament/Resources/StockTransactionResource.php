<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransactionResource\Pages;
use App\Models\StockTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Kartu Stok';
    protected static ?string $modelLabel = 'Transaksi Stok';
    protected static ?string $pluralModelLabel = 'Kartu Stok';

    public static function canAccess(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin') ||
            auth()->user()->hasPermissionTo('manage_inventory')
        );
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')
                    ->label('Tipe'),
                Forms\Components\TextInput::make('qty')
                    ->label('Qty'),
                Forms\Components\TextInput::make('remaining_stock')
                    ->label('Sisa Stok'),
                Forms\Components\Textarea::make('notes')
                    ->label('Keterangan'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Barang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.code')
                    ->label('Kode')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'IN' => 'heroicon-m-arrow-down-left',
                        'OUT' => 'heroicon-m-arrow-up-right',
                        default => 'heroicon-m-minus',
                    }),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('remaining_stock')
                    ->label('Sisa Stok')
                    ->numeric()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Referensi / Supplier / Customer')
                    ->searchable()
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oleh')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Barang')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe')
                    ->options([
                        'IN' => 'Masuk (IN)',
                        'OUT' => 'Keluar (OUT)',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('Periode')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransactions::route('/'),
        ];
    }
}