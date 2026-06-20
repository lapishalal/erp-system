<?php

namespace App\Filament\Widgets;

use App\Models\ProductStock;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class LowStockWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static string $view = 'filament.widgets.low-stock';

    public function getLowStockItems(): array
    {
        return ProductStock::with('product', 'warehouse')
            ->whereHas('product', function (Builder $query) {
                $query->whereColumn('product_stocks.available_stock', '<=', 'products.min_stock');
            })
            ->orWhere('available_stock', '<=', 0)
            ->orderBy('available_stock', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($stock) {
                $minStock = $stock->product?->min_stock ?? 0;
                $isCritical = $stock->available_stock <= 0;
                return [
                    'product_id' => $stock->product_id,
                    'product_code' => $stock->product?->code ?? '-',
                    'product_name' => $stock->product?->name ?? '-',
                    'warehouse' => $stock->warehouse?->name ?? '-',
                    'physical_stock' => $stock->physical_stock,
                    'available_stock' => $stock->available_stock,
                    'min_stock' => $minStock,
                    'is_critical' => $isCritical,
                ];
            })
            ->toArray();
    }

    public function getTotalLowStock(): int
    {
        return ProductStock::whereHas('product', function (Builder $query) {
                $query->whereColumn('product_stocks.available_stock', '<=', 'products.min_stock');
            })
            ->orWhere('available_stock', '<=', 0)
            ->count();
    }
}