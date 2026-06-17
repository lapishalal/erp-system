<?php

use App\Http\Controllers\PosController;
use App\Exports\BalanceSheetExport;
use App\Exports\ProfitLossExport;
use App\Exports\SalesReportExport;
use App\Exports\StockReportExport;
use App\Models\SalesInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

Route::get('/', function () {
    return redirect('/admin');
});

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
    return Excel::download(new ProfitLossExport($year, $month), 'profit-loss-' . $year . '-' . $month . '.xlsx');
})->name('profit-loss.export');

Route::get('/balance-sheet/export', function () {
    $year = request('year', now()->year);
    $month = request('month', now()->month);
    return Excel::download(new BalanceSheetExport($year, $month), 'neraca-' . $year . '-' . $month . '.xlsx');
})->name('balance-sheet.export');

// POS Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/pos/api/products', [PosController::class, 'getProducts']);
    Route::post('/pos/api/checkout', [PosController::class, 'checkout']);
});
Route::get('/pos/{id}/print', [PosController::class, 'printReceipt'])->name('pos.print');

Route::get('/pos-report/export', function () {
    $filters = request()->only(['dari', 'sampai', 'payment_method', 'created_by']);
    return Excel::download(new \App\Exports\PosTransactionExport($filters), 'riwayat-pos-' . now()->format('Ymd') . '.xlsx');
})->name('pos-report.export');

Route::get('/payroll/{payroll}/print', function (\App\Models\Payroll $payroll) {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payroll', compact('payroll'));
    return $pdf->stream('slip-gaji-' . $payroll->payroll_number . '.pdf');
})->name('payroll.print');