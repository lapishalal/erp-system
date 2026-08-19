<?php

namespace App\Services;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\DeliveryOrder;
use App\Models\MarketplaceOrder;
use App\Models\ProductStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyReportService
{
    /**
     * Susun teks laporan harian untuk satu tenant.
     * Semua query difilter tenant_id eksplisit karena CLI tidak punya Auth::user().
     * Setiap seksi dibungkus try/catch agar satu tabel bermasalah tidak menggagalkan seluruh laporan.
     */
    public function buildReport(string $tenantId, ?string $tenantName = null): string
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $startOfMonth = now()->startOfMonth();

        // ===== OMSET & PROFIT =====
        $revenueToday = $this->safe(fn () => SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', $today)->sum('total'), 0);
        $revenueYesterday = $this->safe(fn () => SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', $yesterday)->sum('total'), 0);
        $revenueMtd = $this->safe(fn () => SalesInvoice::where('tenant_id', $tenantId)->whereDate('date', '>=', $startOfMonth)->sum('total'), 0);
        $profitToday = $this->safe(fn () => SalesOrder::where('tenant_id', $tenantId)
            ->whereDate('date', $today)
            ->whereIn('status', ['OPEN', 'PARTIAL', 'COMPLETE'])
            ->sum('profit'), 0);
        $soToday = $this->safe(fn () => SalesOrder::where('tenant_id', $tenantId)->whereDate('date', $today)->count(), 0);
        $soPending = $this->safe(fn () => SalesOrder::where('tenant_id', $tenantId)->whereIn('status', ['OPEN', 'PARTIAL'])->count(), 0);
        $doToday = $this->safe(fn () => DeliveryOrder::where('tenant_id', $tenantId)->whereDate('date', $today)->count(), 0);

        // ===== KAS =====
        $cashInToday = $this->safe(fn () => (float) CashIn::where('tenant_id', $tenantId)->whereDate('date', $today)->sum('amount'), 0.0);
        $cashOutToday = $this->safe(fn () => (float) CashOut::where('tenant_id', $tenantId)->whereDate('date', $today)->sum('amount'), 0.0);
        $totalCashIn = $this->safe(fn () => (float) CashIn::where('tenant_id', $tenantId)->sum('amount'), 0.0);
        $totalCashOut = $this->safe(fn () => (float) CashOut::where('tenant_id', $tenantId)->sum('amount'), 0.0);
        $cashPosition = max(0, $totalCashIn - $totalCashOut);

        // ===== PIUTANG =====
        $receivables = collect();
        $arTotal = 0.0;
        $arOverdue = collect();
        $arOverdueTotal = 0.0;
        $arDueSoon = collect();
        $arDueSoonTotal = 0.0;
        try {
            $receivables = SalesInvoice::where('tenant_id', $tenantId)
                ->where('status', '!=', 'PAID')
                ->get()
                ->filter(fn ($inv) => (float) $inv->total > (float) $inv->paid_amount);
            $arTotal = $receivables->sum(fn ($inv) => (float) $inv->total - (float) $inv->paid_amount);
            $arOverdue = $receivables->filter(fn ($inv) => $inv->due_date && $inv->due_date->lt(now()->startOfDay()));
            $arOverdueTotal = $arOverdue->sum(fn ($inv) => (float) $inv->total - (float) $inv->paid_amount);
            $arDueSoon = $receivables->filter(fn ($inv) => $inv->due_date && $inv->due_date->between(now()->startOfDay(), now()->addDays(7)->endOfDay()));
            $arDueSoonTotal = $arDueSoon->sum(fn ($inv) => (float) $inv->total - (float) $inv->paid_amount);
        } catch (\Exception $e) {
            $this->logError($tenantId, 'piutang', $e);
        }

        // ===== HUTANG & PEMBELIAN =====
        $payables = collect();
        $apTotal = 0.0;
        $poPending = 0;
        $poToday = 0;
        try {
            $payables = PurchaseInvoice::where('tenant_id', $tenantId)
                ->where('status', '!=', 'PAID')
                ->get()
                ->filter(fn ($inv) => (float) $inv->total > (float) $inv->paid_amount);
            $apTotal = $payables->sum(fn ($inv) => (float) $inv->total - (float) $inv->paid_amount);
            $poPending = PurchaseOrder::where('tenant_id', $tenantId)->whereIn('status', ['ORDERED', 'PARTIAL'])->count();
            $poToday = PurchaseOrder::where('tenant_id', $tenantId)->whereDate('date', $today)->count();
        } catch (\Exception $e) {
            $this->logError($tenantId, 'hutang/pembelian', $e);
        }

        // ===== STOK =====
        $stockValue = 0;
        $criticalStock = collect();
        try {
            $stockValue = DB::table('product_stocks')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->where('product_stocks.tenant_id', $tenantId)
                ->selectRaw('SUM(product_stocks.available_stock * COALESCE(products.last_buy_price, 0)) as total_value')
                ->value('total_value') ?? 0;

            $criticalStock = ProductStock::where('product_stocks.tenant_id', $tenantId)
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->whereColumn('product_stocks.available_stock', '<=', 'products.min_stock')
                ->where('products.min_stock', '>', 0)
                ->select('products.name', 'products.code', 'products.min_stock', 'product_stocks.available_stock')
                ->orderBy('products.name')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $this->logError($tenantId, 'stok', $e);
        }

        // ===== MARKETPLACE =====
        $mpPending = 0;
        $mpNeedsReview = 0;
        $mpToday = 0;
        $zeroSettled = 0;
        try {
            $mpPending = MarketplaceOrder::where('tenant_id', $tenantId)->whereNull('sales_order_id')->where('is_hidden', false)->count();
            $mpNeedsReview = MarketplaceOrder::where('tenant_id', $tenantId)->where('needs_review', true)->count();
            $mpToday = MarketplaceOrder::where('tenant_id', $tenantId)
                ->where(function ($q) use ($today) {
                    $q->whereDate('processed_at', $today)->orWhereDate('created_at', $today);
                })
                ->count();

            // Kasus "settlement 0": invoice TikTok PAID tapi cash-in 0 (bekas proses lama)
            $zeroSettled = MarketplaceOrder::where('tenant_id', $tenantId)
                ->where('platform', 'tiktok')
                ->whereNotNull('sales_order_id')
                ->where(function ($q) {
                    $q->where('needs_review', false)->orWhereNull('needs_review');
                })
                ->whereHas('salesOrder.salesInvoices', fn ($q) => $q->where('status', 'PAID'))
                ->whereHas('salesOrder.salesInvoices.cashIns', fn ($q) => $q->where('amount', 0))
                ->count();
        } catch (\Exception $e) {
            $this->logError($tenantId, 'marketplace', $e);
        }

        $lines = [];
        $lines[] = '📊 <b>LAPORAN HARIAN ERP</b>';
        $lines[] = '📅 ' . $this->headerDate();
        if ($tenantName) {
            $lines[] = '🏢 <b>' . e($tenantName) . '</b>';
        }
        $lines[] = '';

        $lines[] = '💵 <b>OMSET & PROFIT</b>';
        $lines[] = '▫️ Omset hari ini: <b>Rp ' . $this->rupiah($revenueToday) . '</b>';
        $lines[] = '▫️ Omset kemarin : Rp ' . $this->rupiah($revenueYesterday);
        $lines[] = '▫️ Profit hari ini: Rp ' . $this->rupiah($profitToday);
        $lines[] = '▫️ Omset bulan ini: Rp ' . $this->rupiah($revenueMtd);
        $lines[] = '▫️ SO baru: ' . $soToday . ' | SO pending: ' . $soPending . ' | DO terkirim: ' . $doToday;
        $lines[] = '';

        $lines[] = '💰 <b>KAS</b>';
        $lines[] = '▫️ Masuk hari ini: Rp ' . $this->rupiah($cashInToday);
        $lines[] = '▫️ Keluar hari ini: Rp ' . $this->rupiah($cashOutToday);
        $lines[] = '▫️ Posisi kas: <b>Rp ' . $this->rupiah($cashPosition) . '</b>';
        $lines[] = '';

        $lines[] = '🧾 <b>PIUTANG</b>';
        $lines[] = '▫️ Total piutang: <b>Rp ' . $this->rupiah($arTotal) . '</b> (' . $receivables->count() . ' faktur)';
        $lines[] = '▫️ Lewat jatuh tempo: Rp ' . $this->rupiah($arOverdueTotal) . ' (' . $arOverdue->count() . ' faktur)';
        $lines[] = '▫️ Jatuh tempo 7 hari: Rp ' . $this->rupiah($arDueSoonTotal) . ' (' . $arDueSoon->count() . ' faktur)';
        $lines[] = '';

        $lines[] = '📦 <b>HUTANG & PEMBELIAN</b>';
        $lines[] = '▫️ Total hutang supplier: Rp ' . $this->rupiah($apTotal) . ' (' . $payables->count() . ' faktur)';
        $lines[] = '▫️ PO pending: ' . $poPending . ' | PO hari ini: ' . $poToday;
        $lines[] = '';

        $lines[] = '📉 <b>STOK</b>';
        $lines[] = '▫️ Nilai stok: Rp ' . $this->rupiah($stockValue);
        $lines[] = '▫️ Stok kritis: <b>' . $criticalStock->count() . '</b> item';
        foreach ($criticalStock->take(3) as $p) {
            $lines[] = '   - ' . e($p->name) . ' (' . (int) $p->available_stock . '/' . (int) $p->min_stock . ')';
        }
        $lines[] = '';

        $lines[] = '🛒 <b>MARKETPLACE</b>';
        $lines[] = '▫️ Order masuk hari ini: ' . $mpToday;
        $lines[] = '▫️ Order belum diproses: ' . $mpPending;
        $lines[] = $mpNeedsReview > 0
            ? '🔴 Butuh review settlement: <b>' . $mpNeedsReview . '</b>'
            : '🟢 Butuh review settlement: 0';
        if ($zeroSettled > 0) {
            $lines[] = '⚠️ Potensi error CashIn=0: <b>' . $zeroSettled . '</b>';
        }

        $lines[] = '';
        $lines[] = '— dibuat otomatis oleh ERP System —';

        return implode("\n", $lines);
    }

    private function safe(\Closure $fn, $default)
    {
        try {
            return $fn();
        } catch (\Exception $e) {
            Log::warning('daily:report seksi gagal', ['error' => $e->getMessage()]);
            return $default;
        }
    }

    private function logError(string $tenantId, string $section, \Exception $e): void
    {
        Log::warning("daily:report seksi {$section} gagal", [
            'tenant_id' => $tenantId,
            'error' => $e->getMessage(),
        ]);
    }

    private function headerDate(): string
    {
        try {
            return now('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i');
        } catch (\Exception $e) {
            return now()->format('d M Y H:i');
        }
    }

    public function rupiah($value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}
