<?php

namespace App\Filament\Pages;

use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\DeliveryOrder;
use App\Models\MarketplaceOrder;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Employee;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EnhancedDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Dashboard Analitik';
    protected static ?string $title = 'Dashboard Analitik Bisnis';
    protected static ?string $slug = 'enhanced-dashboard';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Dasbor';

    protected static string $view = 'filament.pages.enhanced-dashboard';

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

    private function getTenantId(): ?string
    {
        return auth()->user()->tenant_id ?? session('tenant_id');
    }

    public function getStatsCards(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $startOfMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->startOfMonth()->subDay();

        $revenueToday = SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', $today)->sum('total');
        $revenueYesterday = SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', $yesterday)->sum('total');
        $revenueTrend = $revenueYesterday > 0 ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1) : 0;

        $revenueMTD = SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', '>=', $startOfMonth)->sum('total');
        $revenueLastMonth = SalesInvoice::where('tenant_id', $tenantId)->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->sum('total');
        $mtdTrend = $revenueLastMonth > 0 ? round((($revenueMTD - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;

        $totalCashIn = CashIn::where('tenant_id', $tenantId)->sum('amount');
        $totalCashOut = CashOut::where('tenant_id', $tenantId)->sum('amount');
        $cashPosition = max(0, $totalCashIn - $totalCashOut);

        $pendingSO = SalesOrder::where('tenant_id', $tenantId)->whereIn('status', ['OPEN', 'PARTIAL'])->count();
        $pendingPO = PurchaseOrder::where('tenant_id', $tenantId)->whereIn('status', ['ORDERED', 'PARTIAL'])->count();
        $doToday = DeliveryOrder::where('tenant_id', $tenantId)->whereDate('date', $today)->count();
        $soToday = SalesOrder::where('tenant_id', $tenantId)->whereDate('date', $today)->count();

        $stockValue = 0;
        try {
            $stockValue = DB::table('product_stocks')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->where('product_stocks.tenant_id', $tenantId)
                ->selectRaw('SUM(product_stocks.available_stock * COALESCE(products.last_buy_price, 0)) as total_value')
                ->value('total_value') ?? 0;
        } catch (\Exception $e) {}

        $criticalStock = 0;
        try {
            $criticalStock = ProductStock::where('tenant_id', $tenantId)
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->whereColumn('product_stocks.available_stock', '<', 'products.min_stock')
                ->count();
        } catch (\Exception $e) {}

        $pendingMarketplace = 0;
        try {
            $pendingMarketplace = MarketplaceOrder::where('tenant_id', $tenantId)->whereNull('sales_order_id')->count();
        } catch (\Exception $e) {}

        $activeEmployees = 0;
        try {
            $activeEmployees = Employee::where('tenant_id', $tenantId)->where('is_active', true)->count();
        } catch (\Exception $e) {}

        return [
            'cash_position' => $cashPosition,
            'revenue_today' => $revenueToday,
            'revenue_yesterday' => $revenueYesterday,
            'revenue_trend' => $revenueTrend,
            'revenue_mtd' => $revenueMTD,
            'revenue_last_month' => $revenueLastMonth,
            'mtd_trend' => $mtdTrend,
            'so_today' => $soToday,
            'so_pending' => $pendingSO,
            'do_today' => $doToday,
            'po_pending' => $pendingPO,
            'stock_value' => $stockValue,
            'critical_stock' => $criticalStock,
            'pending_marketplace' => $pendingMarketplace,
            'active_employees' => $activeEmployees,
        ];
    }

    public function getRevenueTrendData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        $revenueData = SalesInvoice::where('tenant_id', $tenantId)
            ->whereDate('date', '>=', now()->subDays(30))
            ->selectRaw('DATE(date) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->parse($date)->format('d M');
            $revenue = $revenueData->firstWhere('date', $date)?->revenue ?? 0;
            $data[] = (float) $revenue;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function getSalesByChannelData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        $salesBySource = SalesOrder::where('tenant_id', $tenantId)
            ->whereMonth('date', now()->month)->whereYear('date', now()->year)
            ->select('source', DB::raw('SUM(total_amount) as total'))
            ->groupBy('source')->get();

        return [
            'labels' => $salesBySource->pluck('source')->map(fn($s) => ucfirst($s))->toArray(),
            'data' => $salesBySource->pluck('total')->map(fn($t) => (float) $t)->toArray(),
        ];
    }

    public function getTopProductsData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        try {
            $topProducts = DB::table('sales_order_details')
                ->join('sales_orders', 'sales_order_details.so_id', '=', 'sales_orders.id')
                ->join('products', 'sales_order_details.product_id', '=', 'products.id')
                ->where('sales_orders.tenant_id', $tenantId)
                ->whereMonth('sales_orders.date', now()->month)->whereYear('sales_orders.date', now()->year)
                ->select('products.name', DB::raw('SUM(sales_order_details.qty * sales_order_details.price) as revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('revenue')->limit(10)->get();

            return [
                'labels' => $topProducts->pluck('name')->toArray(),
                'data' => $topProducts->pluck('revenue')->map(fn($r) => (float) $r)->toArray(),
            ];
        } catch (\Exception $e) {
            return ['labels' => [], 'data' => []];
        }
    }

    public function getArAgingData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        $now = now();
        $invoices = SalesInvoice::where('tenant_id', $tenantId)
            ->where('status', '!=', 'PAID')
            ->where('total', '>', DB::raw('paid_amount'))->get();

        $aging = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'above_90' => 0];

        foreach ($invoices as $inv) {
            $overdue = $inv->due_date->diffInDays($now, false);
            $outstanding = $inv->total - $inv->paid_amount;
            if ($overdue < 0) $aging['current'] += $outstanding;
            elseif ($overdue <= 30) $aging['1_30'] += $outstanding;
            elseif ($overdue <= 60) $aging['31_60'] += $outstanding;
            elseif ($overdue <= 90) $aging['61_90'] += $outstanding;
            else $aging['above_90'] += $outstanding;
        }
        return $aging;
    }

    public function getMonthlyComparisonData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'this_year' => [], 'last_year' => []];

        $thisYear = SalesInvoice::where('tenant_id', $tenantId)->whereYear('date', now()->year)
            ->selectRaw('MONTH(date) as month, SUM(total) as revenue')->groupBy('month')
            ->pluck('revenue', 'month');

        $lastYear = SalesInvoice::where('tenant_id', $tenantId)->whereYear('date', now()->year - 1)
            ->selectRaw('MONTH(date) as month, SUM(total) as revenue')->groupBy('month')
            ->pluck('revenue', 'month');

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $thisYearData = [];
        $lastYearData = [];
        for ($i = 1; $i <= 12; $i++) {
            $thisYearData[] = (float) ($thisYear[$i] ?? 0);
            $lastYearData[] = (float) ($lastYear[$i] ?? 0);
        }
        return ['labels' => $labels, 'this_year' => $thisYearData, 'last_year' => $lastYearData];
    }

    public function getExpenseBreakdownData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        try {
            $expenses = CashOut::where('tenant_id', $tenantId)
                ->whereMonth('date', now()->month)->whereYear('date', now()->year)
                ->join('expense_categories', 'cash_out.category_id', '=', 'expense_categories.id')
                ->select('expense_categories.name', DB::raw('SUM(cash_out.amount) as total'))
                ->groupBy('expense_categories.id', 'expense_categories.name')->get();

            return [
                'labels' => $expenses->pluck('name')->toArray(),
                'data' => $expenses->pluck('total')->map(fn($t) => (float) $t)->toArray(),
            ];
        } catch (\Exception $e) {
            return ['labels' => [], 'data' => []];
        }
    }

    public function getUpcomingPayments(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        try {
            $upcoming = SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', '!=', 'PAID')
                ->whereBetween('due_date', [now(), now()->addDays(7)])
                ->with('customer')->orderBy('due_date')->limit(5)->get();

            return $upcoming->map(fn($inv) => [
                'type' => 'receivable',
                'number' => $inv->invoice_number,
                'party' => $inv->customer?->name ?? 'N/A',
                'amount' => $inv->total - $inv->paid_amount,
                'due_date' => $inv->due_date->format('d M Y'),
                'days_left' => max(0, now()->startOfDay()->diffInDays($inv->due_date)),
            ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getLowStockProducts(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        try {
            return ProductStock::where('product_stocks.tenant_id', $tenantId)
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->leftJoin('warehouses', 'product_stocks.warehouse_id', '=', 'warehouses.id')
                ->whereColumn('product_stocks.available_stock', '<=', 'products.min_stock')
                ->select(
                    'products.id', 'products.name', 'products.code',
                    'products.min_stock', 'products.last_buy_price',
                    'product_stocks.available_stock', 'product_stocks.physical_stock',
                    'warehouses.name as warehouse_name',
                    DB::raw('GREATEST(products.min_stock - product_stocks.available_stock, 0) as shortage')
                )
                ->orderBy('shortage', 'desc')->limit(10)->get()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPendingPOs(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        try {
            $pendingPOs = PurchaseOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['ORDERED', 'PARTIAL'])->with('supplier')
                ->orderBy('created_at', 'desc')->limit(5)->get();

            return $pendingPOs->map(fn($po) => [
                'id' => $po->id,
                'number' => $po->po_number,
                'supplier' => $po->supplier?->name ?? 'N/A',
                'total' => $po->total_amount,
                'date' => $po->date->format('d M Y'),
            ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRecentTransactions(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return [];

        try {
            $cashIns = CashIn::where('tenant_id', $tenantId)->where('amount', '>=', 1000000)
                ->orderBy('created_at', 'desc')->limit(5)->get()
                ->map(fn($ci) => [
                    'type' => 'income', 'date' => $ci->date->format('d M Y'),
                    'description' => $ci->description ?? $ci->type, 'amount' => $ci->amount,
                ]);

            $cashOuts = CashOut::where('tenant_id', $tenantId)->where('amount', '>=', 1000000)
                ->orderBy('created_at', 'desc')->limit(5)->get()
                ->map(fn($co) => [
                    'type' => 'expense', 'date' => $co->date->format('d M Y'),
                    'description' => $co->description ?? ($co->category?->name ?? 'Cash Out'), 'amount' => $co->amount,
                ]);

            $combined = $cashIns->merge($cashOuts)->sortByDesc('date')->take(10)->values();
            return $combined->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSalesOrderStatusData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        $statuses = SalesOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->get();

        return [
            'labels' => $statuses->pluck('status')->toArray(),
            'data' => $statuses->pluck('total')->map(fn($t) => (int) $t)->toArray(),
        ];
    }

    public function getTopCustomersData(): array
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) return ['labels' => [], 'data' => []];

        try {
            $top = Customer::where('customers.tenant_id', $tenantId)
                ->leftJoin('sales_orders', function ($j) use ($tenantId) {
                    $j->on('sales_orders.customer_id', '=', 'customers.id')
                        ->where('sales_orders.tenant_id', '=', $tenantId)
                        ->whereIn('sales_orders.status', ['OPEN', 'PARTIAL', 'COMPLETE']);
                })
                ->select('customers.name', DB::raw('COALESCE(SUM(sales_orders.total_amount), 0) as total'))
                ->groupBy('customers.id', 'customers.name')
                ->orderByDesc('total')->limit(5)->get();

            return [
                'labels' => $top->pluck('name')->toArray(),
                'data' => $top->pluck('total')->map(fn($t) => (float) $t)->toArray(),
            ];
        } catch (\Exception $e) {
            return ['labels' => [], 'data' => []];
        }
    }

}
