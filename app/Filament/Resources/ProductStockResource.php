<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductStockResource\Pages;
use App\Models\ProductStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductStockResource extends Resource
{
    protected static ?string $model = ProductStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Cek Stok';
    protected static ?string $modelLabel = 'Stok Barang';
    protected static ?string $pluralModelLabel = 'Stok Barang';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_inventory');
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('physical_stock')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('outstanding_stock')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('available_stock')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.brand.name')
                    ->label('Brand'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang'),
                Tables\Columns\TextColumn::make('physical_stock')
                    ->label('Stok Fisik')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding_stock')
                    ->label('Outstanding')
                    ->numeric(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->numeric()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('product.min_stock')
                    ->label('Min Stok')
                    ->numeric(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stok Menipis')
                    ->query(fn ($query) => 
                        $query->whereColumn('available_stock', '<=', 'products.min_stock')
                            ->join('products', 'product_stocks.product_id', '=', 'products.id')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductStocks::route('/'),
            'view' => Pages\ViewProductStock::route('/{record}'),
        ];
    }
}