<?php

use App\Http\Controllers\PosController;
use App\Http\Controllers\TikTokImportController;
use App\Exports\BalanceSheetExport;
use App\Exports\ProfitLossExport;
use App\Exports\SalesReportExport;
use App\Exports\StockReportExport;
use App\Exports\CashFlowExport; // <-- TAMBAH INI
use App\Models\SalesInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use App\Filament\Pages\RegisterCompany;
use Maatwebsite\Excel\Facades\Excel; // Ensure Excel facade is imported

Route::get('/admin/register', RegisterCompany::class)
    ->name('filament.admin.auth.register')
    ->middleware([
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);

Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

Route::get('/', function () {
    return redirect('/admin');
});

// =====================================================================
// PERBAIKAN (PRIORITAS RENDAH):
// Menambahkan middleware 'auth' untuk route-route export laporan keuangan
// guna mencegah kebocoran data sensitif perusahaan kepada publik tanpa login.
// =====================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/invoice/{invoice}/print', function (SalesInvoice $invoice) {
        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    })->name('invoice.print');

    Route::get('/sales-report/export', function () {
        $filters = request()->only(['dari', 'sampai', 'customer_id', 'status', 'brand_id', 'product_id']);
        return Excel::download(new SalesReportExport($filters), 'laporan-penjualan-' . now()->format('Ymd') . '.xlsx');
    })->name('sales-report.export');

    Route::get('/stock-report/export', function () {
        $filters = request()->only(['dari', 'sampai', 'warehouse_id', 'product_id', 'type']);
        return Excel::download(new StockReportExport($filters), 'laporan-stok-' . now()->format('Ymd') . '.xlsx');
    })->name('stock-report.export');

    Route::get('/profit-loss/export', function () {
        $year = request('year', now()->year);
        $month = request('month', now()->month);
        return Excel::download(new ProfitLossExport((int)$year, (int)$month), 'profit-loss-' . $year . '-' . $month . '.xlsx');
    })->name('profit-loss.export');

    Route::get('/balance-sheet/export', function () {
        $year = request('year', now()->year);
        $month = request('month', now()->month);
        return Excel::download(new BalanceSheetExport((int)$year, (int)$month), 'neraca-' . $year . '-' . $month . '.xlsx');
    })->name('balance-sheet.export');

    // =====================================================================
    // PERBAIKAN AKUNTANSI (PRIORITAS TINGGI):
    // Menambahkan route export excel khusus untuk laporan Cash Flow (Arus Kas).
    // Sebelumnya, tombol export di halaman cash flow mengarah salah ke profit-loss.
    // =====================================================================
    Route::get('/cash-flow/export', function () {
        $year = request('year', now()->year);
        $month = request('month', now()->month);
        return Excel::download(new CashFlowExport((int)$year, (int)$month), 'cash-flow-' . $year . '-' . $month . '.xlsx');
    })->name('cash-flow.export');

    // POS Routes
    Route::get('/pos/api/products', [PosController::class, 'getProducts']);
    Route::post('/pos/api/checkout', [PosController::class, 'checkout']);
    Route::get('/pos/{id}/print', [PosController::class, 'printReceipt'])->name('pos.print');

    Route::get('/pos-report/export', function () {
        $filters = request()->only(['dari', 'sampai', 'payment_method', 'created_by']);
        return Excel::download(new \App\Exports\PosTransactionExport($filters), 'riwayat-pos-' . now()->format('Ymd') . '.xlsx');
    })->name('pos-report.export');

    Route::get('/payroll/{payroll}/print', function (\App\Models\Payroll $payroll) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payroll', compact('payroll'));
        return $pdf->stream('slip-gaji-' . $payroll->payroll_number . '.pdf');
    })->name('payroll.print');

    // TikTok Import API Routes
    Route::post('/tiktok-import/orders', [TikTokImportController::class, 'importOrders']);
    Route::post('/tiktok-import/income', [TikTokImportController::class, 'importIncome']);
    Route::post('/tiktok-import/map-item', [TikTokImportController::class, 'mapItem']);
    Route::post('/tiktok-import/map-item-new-product', [TikTokImportController::class, 'mapItemWithNewProduct']);
    Route::post('/tiktok-import/auto-match-all', [TikTokImportController::class, 'autoMatchAll']);
    Route::post('/tiktok-import/process-mapped-orders', [TikTokImportController::class, 'processMappedOrders']);
    Route::get('/tiktok-import/unmapped-items', [TikTokImportController::class, 'getUnmappedItems']);
});
