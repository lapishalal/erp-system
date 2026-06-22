<?php

namespace App\Filament\Pages;

use App\Models\DeliveryOrder;
use App\Models\MarketplaceOrder;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\ProductStock;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ExecutiveDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Executive Dashboard';
    protected static ?string $title = 'Executive Dashboard';
    protected static ?string $slug = 'executive-dashboard';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationGroup = 'Dasbor';

    protected static string $view = 'filament.pages.executive-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

    public function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id ?? session('tenant_id');

        if (!$tenantId) {
            return [];
        }

        $today = now()->startOfDay();

        // Sales hari ini per channel
        $salesToday = SalesOrder::where('tenant_id', $tenantId)
            ->where('date', '>=', $today)
            ->select('source', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->toArray();

        // Total order marketplace pending
        $pendingMarketplace = MarketplaceOrder::where('tenant_id', $tenantId)
            ->whereNull('sales_order_id')
            ->count();

        // Stok kritis (available_stock < 10)
        $criticalStock = ProductStock::where('tenant_id', $tenantId)
            ->where('available_stock', '<', 10)
            ->count();

        // DO hari ini
        $doToday = DeliveryOrder::where('tenant_id', $tenantId)
            ->where('date', '>=', $today)
            ->count();

        return [
            'sales_today' => $salesToday,
            'pending_marketplace' => $pendingMarketplace,
            'critical_stock' => $criticalStock,
            'do_today' => $doToday,
            'total_revenue_today' => array_sum($salesToday),
        ];
    }

    public function getRecentOrders(): array
    {
        $tenantId = auth()->user()->tenant_id ?? session('tenant_id');

        return SalesOrder::where('tenant_id', $tenantId)
            ->whereIn('source', ['tiktok', 'shopee'])
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getUnmappedOrders(): array
    {
        $tenantId = auth()->user()->tenant_id ?? session('tenant_id');

        return MarketplaceOrder::where('tenant_id', $tenantId)
            ->where('is_mapped', false)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }
}