<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $query = SalesOrder::with(['customer', 'details.product.brand'])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']);

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->has('brand_id')) {
            $query->whereHas('details.product', fn($q) => $q->where('brand_id', $request->brand_id));
        }
        if ($request->has('product_id')) {
            $query->whereHas('details', fn($q) => $q->where('product_id', $request->product_id));
        }

        $data = $query->orderBy('date', 'desc')->get();

        $summary = [
            'total_orders' => $data->count(),
            'total_qty' => $data->sum('total_qty'),
            'total_omset' => $data->sum('total_amount'),
            'total_profit' => $data->sum('profit'),
        ];

        return response()->json([
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function purchaseVsSales(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $sales = SalesOrder::selectRaw('MONTH(date) as month, SUM(total_amount) as total')
            ->whereYear('date', $year)
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = \App\Models\PurchaseOrder::selectRaw('MONTH(date) as month, SUM(total_amount) as total')
            ->whereYear('date', $year)
            ->whereIn('status', ['ORDERED', 'PARTIAL', 'COMPLETE'])
            ->groupBy('month')
            ->pluck('total', 'month');

        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $sale = $sales[$i] ?? 0;
            $purchase = $purchases[$i] ?? 0;
            $result[] = [
                'month' => date('M', mktime(0, 0, 0, $i, 1)),
                'purchase' => $purchase,
                'sales' => $sale,
                'margin' => $sale - $purchase,
            ];
        }

        return response()->json($result);
    }

    public function customerReport(Request $request)
    {
        $query = Customer::withCount(['salesOrders as total_orders' => function ($q) {
            $q->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']);
        }])
        ->withSum(['salesOrders as total_omset' => function ($q) {
            $q->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']);
        }], 'total_amount')
        ->withSum(['salesOrders as total_profit' => function ($q) {
            $q->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']);
        }], 'profit');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->orderByDesc('total_omset')->paginate(20));
    }

    public function stockReport(Request $request)
    {
        $query = ProductStock::with(['product.brand', 'warehouse']);

        if ($request->has('low_stock')) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('product_stocks.physical_stock', '<=', 'products.min_stock')
                  ->where('products.min_stock', '>', 0);
            });
        }

        if ($request->has('zero_stock')) {
            $query->where('physical_stock', 0);
        }

        $data = $query->get();

        $summary = [
            'total_items' => $data->count(),
            'total_stock_value' => $data->sum(function ($item) {
                return $item->physical_stock * ($item->product->last_buy_price ?? 0);
            }),
            'low_stock_count' => $data->filter(function ($item) {
                return $item->physical_stock <= $item->product->min_stock && $item->product->min_stock > 0;
            })->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function trendingProducts(Request $request)
    {
        $period = $request->get('period', 'monthly'); // daily, weekly, monthly, yearly
        $groupBy = $request->get('group_by', 'product'); // product, brand, category

        $query = SalesOrderDetail::with('product.brand', 'product.category')
            ->whereHas('salesOrder', function ($q) {
                $q->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']);
            });

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'yearly':
                $query->whereYear('created_at', now()->year);
                break;
            default: // monthly
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
        }

        if ($groupBy === 'brand') {
            $data = $query->get()->groupBy('product.brand.name')
                ->map(fn($items, $name) => [
                    'name' => $name,
                    'qty' => $items->sum('qty'),
                    'omset' => $items->sum('subtotal'),
                ])->sortByDesc('qty')->values();
        } elseif ($groupBy === 'category') {
            $data = $query->get()->groupBy('product.category.name')
                ->map(fn($items, $name) => [
                    'name' => $name,
                    'qty' => $items->sum('qty'),
                    'omset' => $items->sum('subtotal'),
                ])->sortByDesc('qty')->values();
        } else {
            $data = $query->selectRaw('product_id, SUM(qty) as total_qty, SUM(subtotal) as total_omset')
                ->groupBy('product_id')
                ->with('product')
                ->orderByDesc('total_qty')
                ->limit(20)
                ->get();
        }

        return response()->json($data);
    }

    public function profitLoss(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        // Pendapatan
        $revenue = SalesOrder::whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('total_amount');

        // HPP
        $hpp = SalesOrder::whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('total_cost');

        // Expense
        $expenses = \App\Models\CashOut::whereBetween('date', [$dateFrom, $dateTo])
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->get();

        $grossProfit = $revenue - $hpp;
        $totalExpense = $expenses->sum('total');
        $netProfit = $grossProfit - $totalExpense;

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'revenue' => $revenue,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
        ]);
    }

    public function brandPerformance(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        $brands = Brand::withSum(['products.salesOrderDetails as total_qty' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('salesOrder', fn($sq) => $sq->whereBetween('date', [$dateFrom, $dateTo])
                ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']));
        }], 'qty')
        ->withSum(['products.salesOrderDetails as total_omset' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('salesOrder', fn($sq) => $sq->whereBetween('date', [$dateFrom, $dateTo])
                ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']));
        }], 'subtotal')
        ->withSum(['products.salesOrderDetails as total_profit' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('salesOrder', fn($sq) => $sq->whereBetween('date', [$dateFrom, $dateTo])
                ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE']));
        }], 'profit')
        ->get()
        ->map(fn($b) => [
            'brand' => $b->name,
            'qty' => $b->total_qty ?? 0,
            'omset' => $b->total_omset ?? 0,
            'profit' => $b->total_profit ?? 0,
        ]);

        return response()->json($brands);
    }
}