<?php

namespace App\Filament\Widgets;

use App\Models\Brand;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopBrandsTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top Brand';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('view_reports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Brand::query()
                    ->select('brands.*')
                    ->selectRaw('COALESCE((SELECT SUM(sales_order_details.qty) FROM sales_order_details JOIN sales_orders ON sales_orders.id = sales_order_details.so_id WHERE sales_order_details.product_id IN (SELECT id FROM products WHERE products.brand_id = brands.id) AND sales_orders.status IN (\'OPEN\',\'PARTIAL\',\'COMPLETE\')), 0) as total_qty')
                    ->selectRaw('COALESCE((SELECT SUM(sales_orders.total_amount) FROM sales_orders JOIN sales_order_details ON sales_orders.id = sales_order_details.so_id WHERE sales_order_details.product_id IN (SELECT id FROM products WHERE products.brand_id = brands.id) AND sales_orders.status IN (\'OPEN\',\'PARTIAL\',\'COMPLETE\')), 0) as total_omset')
                    ->orderByDesc('total_omset')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Brand'),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qty Terjual'),
                Tables\Columns\TextColumn::make('total_omset')
                    ->label('Omset')
                    ->money('IDR'),
            ])
            ->paginated(false);
    }
}