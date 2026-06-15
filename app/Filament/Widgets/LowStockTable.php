<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Barang Hampir Habis';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('view_reports') || auth()->user()->hasPermissionTo('manage_inventory');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->join('product_stocks', 'products.id', '=', 'product_stocks.product_id')
                    ->whereColumn('product_stocks.physical_stock', '<=', 'products.min_stock')
                    ->where('products.min_stock', '>', 0)
                    ->select('products.*', 'product_stocks.physical_stock as current_stock')
                    ->orderBy('product_stocks.physical_stock')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk'),
                Tables\Columns\TextColumn::make('brand.name'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini'),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Min Stok'),
            ])
            ->paginated(false);
    }
}