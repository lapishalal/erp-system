<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\ProductStock;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary()
    {
        $today = now()->startOfDay();
        $endToday = now()->endOfDay();

        $omsetToday = SalesOrder::whereBetween('date', [$today, $endToday])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('total_amount');

        $profitToday = SalesOrder::whereBetween('date', [$today, $endToday])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('profit');

        $outstanding = SalesOrder::whereIn('status', ['OPEN', 'PARTIAL'])
            ->sum('total_amount');

        $cashIn = CashIn::sum('amount');
        $cashOut = CashOut::sum('amount');
        $cashPosition = $cashIn - $cashOut;

        $lowStock = ProductStock::with('product')
            ->whereHas('product', function ($q) {
                $q->whereColumn('product_stocks.physical_stock', '<=', 'products.min_stock')
                  ->where('products.min_stock', '>', 0);
            })
            ->count();

        return response()->json([
            'omset_today' => $omsetToday,
            'profit_today' => $profitToday,
            'outstanding_order' => $outstanding,
            'cash_position' => $cashPosition,
            'low_stock_count' => $lowStock,
        ]);
    }
}