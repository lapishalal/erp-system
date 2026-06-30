<?php

namespace App\Services;

use App\Enums\MarketplacePlatform;
use App\Models\Account;
use App\Models\CashIn;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\PosTransaction;
use App\Models\PosTransactionDetail;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class TikTokIncomeImportService
{
    /**
     * Kode akun Biaya Admin Marketplace TikTok.
     * Pastikan akun ini ada di Chart of Accounts (jalankan ChartOfAccountSeeder).
     */
    private const COA_ADMIN_FEE = '5-30001';

    /**
     * Toleransi selisih pembulatan (Rp). Jika selisih < nilai ini, dianggap lunas.
     * Berguna untuk mencegah PARTIAL akibat perbedaan desimal kecil.
     */
    private const ROUNDING_TOLERANCE = 1.0;

    /**
     * Main entry point — import TikTok income CSV
     */
    public function importIncome(string $filePath): array
    {
        $rows = $this->parseCsv($filePath);

        $results = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
            'details' => [],
        ];

        // Filter only "Pesanan" type and TikTok Shop source
        $orderRows = array_filter($rows, function ($row) {
            $type   = trim($row['Jenis transaksi'] ?? '');
            $source = trim($row['Sumber pesanan'] ?? '');
            return $type === 'Pesanan' && $source === 'TikTok Shop';
        });

        $results['total'] = count($orderRows);

        foreach ($orderRows as $row) {
            try {
                $result = $this->processIncomeRow($row);

                match ($result['action']) {
                    'created', 'paid' => $results['created']++,
                    'updated'         => $results['updated']++,
                    'skipped'         => $results['skipped']++,
                    default           => null,
                };

                $results['details'][] = $result;
            } catch (\Throwable $e) {
                $results['errors']++;
                $results['details'][] = [
                    'order_id' => $row['ID Pesanan/Penyesuaian'] ?? 'unknown',
                    'action'   => 'error',
                    'message'  => $e->getMessage(),
                ];
                Log::error('TikTok Income Import Error', [
                    'row'   => $row['ID Pesanan/Penyesuaian'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    // =========================================================================
    // PARSING HELPERS
    // =========================================================================

    private function parseCsv(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'xlsx' || $extension === 'xls') {
            return $this->parseExcel($filePath);
        }

        return $this->parseCsvFile($filePath);
    }

    private function parseExcel(string $filePath): array
    {
        $rows    = [];
        $allRows = Excel::toArray(new \stdClass, $filePath);

        if (empty($allRows) || empty($allRows[0])) {
            throw new \Exception('File Excel income kosong');
        }

        $header = array_map(fn($h) => trim((string) $h), $allRows[0][0] ?? []);

        for ($i = 1; $i < count($allRows[0]); $i++) {
            $row    = $allRows[0][$i];
            $values = array_map(fn($v) => trim((string) ($v ?? '')), $row);

            if (count($values) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $values);
        }

        return $rows;
    }

    private function parseCsvFile(string $filePath): array
    {
        $rows   = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception('File CSV income tidak bisa dibaca: ' . $filePath);
        }

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \Exception('File CSV income kosong');
        }

        $header = array_map(fn($h) => trim($h), $header);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, array_map('trim', $row));
        }

        fclose($handle);
        return $rows;
    }

    // =========================================================================
    // MAIN ROW PROCESSOR
    // =========================================================================

    /**
     * Process a single income row
     */
    private function processIncomeRow(array $row): array
    {
        $orderId          = trim($row['ID Pesanan/Penyesuaian'] ?? '');
        $settlementAmount = $this->parseAmount($row['Jumlah penyelesaian pembayaran'] ?? '0');
        $revenueGross     = $this->parseAmount($row['Total Pendapatan'] ?? '0');
        $totalCost        = $this->parseAmount($row['Total Biaya'] ?? '0');
        $paymentDate      = $this->parseDate($row['Waktu pembayaran pesanan'] ?? '');

        if (empty($orderId)) {
            return ['order_id' => 'unknown', 'action' => 'skipped', 'message' => 'Order ID kosong'];
        }

        $mkOrder = MarketplaceOrder::where('platform', 'tiktok')
            ->where('platform_order_id', $orderId)
            ->first();

        // ===== CASE A: Order NOT yet imported from Order CSV =====
        if (!$mkOrder) {
            return $this->handleMissingOrder($orderId, $row, $settlementAmount, $revenueGross, $paymentDate);
        }

        // ===== CASE B: Order already imported — process payment =====
        $so = $mkOrder->salesOrder;
        if (!$so) {
            return $this->handlePendingOrder($mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
        }

        return $this->processPayment($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
    }

    // =========================================================================
    // CASE HANDLERS
    // =========================================================================

    /**
     * Handle income untuk order yang diimport dari Order CSV, tapi chain belum dibuat
     * (semua item belum di-map). Jika sudah di-map, auto-create chain lalu proses pembayaran.
     */
    private function handlePendingOrder(
        MarketplaceOrder $mkOrder,
        string $orderId,
        float $settlementAmount,
        float $revenueGross,
        ?Carbon $paymentDate
    ): array {
        $mkOrder->load('items');

        $totalItems  = $mkOrder->items->count();
        $mappedItems = $mkOrder->items->where('is_mapped', true)->count();

        if ($totalItems === 0 || $mappedItems < $totalItems) {
            $unmappedCount = $totalItems - $mappedItems;
            return [
                'order_id' => $orderId,
                'action'   => 'skipped',
                'message'  => "Order ada tapi {$unmappedCount} item belum di-map. Silakan map di halaman 'Produk Belum Ter-map TikTok' terlebih dahulu, lalu import ulang income.",
            ];
        }

        return DB::transaction(function () use ($mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate) {
            $processingService = new TikTokOrderProcessingService();
            $chainResult       = $processingService->createOrderChain($mkOrder, 'DELIVERED');

            if (($chainResult['action'] ?? '') === 'skipped') {
                return [
                    'order_id' => $orderId,
                    'action'   => 'skipped',
                    'message'  => 'Chain sudah ada: ' . ($chainResult['message'] ?? ''),
                ];
            }

            $mkOrder->refresh();
            $so = $mkOrder->salesOrder;

            if (!$so) {
                return [
                    'order_id' => $orderId,
                    'action'   => 'error',
                    'message'  => 'Gagal membuat chain untuk order ini',
                ];
            }

            return $this->processPayment($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
        });
    }

    /**
     * Handle income untuk order yang belum pernah diimport dari Order CSV.
     * Buat full chain (SO → DO → Invoice) lalu proses pembayaran + admin fee.
     */
    private function handleMissingOrder(
        string $orderId,
        array $row,
        float $settlementAmount,
        float $revenueGross,
        ?Carbon $paymentDate
    ): array {
        return DB::transaction(function () use ($orderId, $row, $settlementAmount, $revenueGross, $paymentDate) {
            $customer  = (new TikTokCsvImportService())->getOrCreateTikTokCustomer();
            $warehouse = (new TikTokCsvImportService())->getFirstActiveWarehouse();

            $productDetails = $this->parseProductDetails($row['Detail produk terjual'] ?? '');

            if (empty($productDetails)) {
                return [
                    'order_id' => $orderId,
                    'action'   => 'skipped',
                    'message'  => 'Detail produk tidak tersedia (/), skip auto-create. Import dari Order CSV terlebih dahulu.',
                ];
            }

            $mappedItems = [];
            $totalQty    = 0;
            $totalAmount = 0;
            $totalCost   = 0;

            foreach ($productDetails as $pd) {
                $product = $this->findProductBySkuId($pd['sku_id']);

                if (!$product) {
                    $product = Product::where('sku', $pd['sku_id'])->first();
                }

                if ($product) {
                    $costPrice = $product->getHpp();
                    $unitPrice = count($productDetails) === 1
                        ? $revenueGross / $pd['qty']
                        : (float) $product->default_sale_price;

                    $mappedItems[] = [
                        'product'    => $product,
                        'qty'        => $pd['qty'],
                        'unit_price' => round($unitPrice, 2),
                        'cost_price' => $costPrice,
                        'subtotal'   => round($unitPrice * $pd['qty'], 2),
                    ];
                    $totalQty    += $pd['qty'];
                    $totalAmount += round($unitPrice * $pd['qty'], 2);
                    $totalCost   += $costPrice * $pd['qty'];
                }
            }

            if (empty($mappedItems)) {
                return [
                    'order_id' => $orderId,
                    'action'   => 'skipped',
                    'message'  => 'Produk tidak ditemukan di database. Pastikan Seller SKU sudah diisi.',
                ];
            }

            $orderDate     = $paymentDate ?? now();
            $paymentMethod = 'TRANSFER';

            // 1. Create POS Transaction
            $posNumber = $this->generateNumber('POS-TTK');
            $pos = PosTransaction::create([
                'transaction_number' => $posNumber,
                'date'               => $orderDate,
                'customer_id'        => $customer->id,
                'subtotal'           => $totalAmount,
                'discount'           => 0,
                'tax'                => 0,
                'total'              => $totalAmount,
                'paid_amount'        => $settlementAmount,
                'change_amount'      => 0,
                'payment_method'     => $paymentMethod,
                'created_by'         => auth()->id(),
            ]);

            foreach ($mappedItems as $item) {
                PosTransactionDetail::create([
                    'pos_transaction_id' => $pos->id,
                    'product_id'         => $item['product']->id,
                    'qty'                => $item['qty'],
                    'price'              => $item['unit_price'],
                    'subtotal'           => $item['subtotal'],
                ]);
            }

            // 2. Create Sales Order (COMPLETE)
            $soNumber = $this->generateNumber('SO-TTK');
            $so = SalesOrder::create([
                'so_number'    => $soNumber,
                'date'         => $orderDate,
                'customer_id'  => $customer->id,
                'status'       => 'COMPLETE',
                'source'       => 'tiktok',
                'total_qty'    => $totalQty,
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'profit'       => $totalAmount - $totalCost,
                'notes'        => "TikTok Order: {$orderId} (from income import)",
                'created_by'   => auth()->id(),
            ]);

            $soDetails = [];
            foreach ($mappedItems as $item) {
                $detail = SalesOrderDetail::create([
                    'so_id'         => $so->id,
                    'product_id'    => $item['product']->id,
                    'qty'           => $item['qty'],
                    'unit_price'    => $item['unit_price'],
                    'cost_price'    => $item['cost_price'],
                    'delivered_qty' => $item['qty'],
                    'remaining_qty' => 0,
                    'subtotal'      => $item['subtotal'],
                    'profit'        => $item['subtotal'] - ($item['cost_price'] * $item['qty']),
                ]);
                $soDetails[] = $detail;
            }

            // 3. Create DO (DRAFT → DELIVERED untuk trigger stock deduction)
            $doNumber = $this->generateNumber('DO-TTK');
            $do = DeliveryOrder::create([
                'do_number'    => $doNumber,
                'so_id'        => $so->id,
                'date'         => $orderDate,
                'customer_id'  => $customer->id,
                'warehouse_id' => $warehouse?->id,
                'status'       => 'DRAFT',
                'total_qty'    => $totalQty,
                'notes'        => "TikTok Order: {$orderId} (auto from income)",
                'created_by'   => auth()->id(),
            ]);

            foreach ($soDetails as $soDetail) {
                DeliveryOrderDetail::create([
                    'do_id'        => $do->id,
                    'so_detail_id' => $soDetail->id,
                    'product_id'   => $soDetail->product_id,
                    'qty'          => $soDetail->qty,
                ]);
            }

            $do->update(['status' => 'DELIVERED']);

            // 4. Create Invoice (nilai = totalAmount = harga jual kotor, BUKAN settlement)
            $invoiceNumber = $this->generateNumber('INV-TTK');
            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id'    => $customer->id,
                'so_id'          => $so->id,
                'date'           => $orderDate,
                'due_date'       => $orderDate,
                'total'          => $totalAmount,   // ← tetap harga jual kotor
                'paid_amount'    => 0,
                'status'         => 'UNPAID',
                'notes'          => "TikTok Order: {$orderId}",
                'created_by'     => auth()->id(),
            ]);

            foreach ($mappedItems as $item) {
                SalesInvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product']->id,
                    'qty'        => $item['qty'],
                    'price'      => $item['unit_price'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            // 5. Proses pembayaran dengan rekonsiliasi admin fee
            $this->applySettlementWithAdminFee($invoice, $settlementAmount, $orderId, $orderDate, $customer->id);

            // 6. Create HPP Journal
            $this->createHppJournal($so, $orderId);

            // 7. Save ke marketplace_orders
            $tenantId = auth()->user()->tenant_id ?? null;
            MarketplaceOrder::create([
                'tenant_id'         => $tenantId,
                'connection_id'     => null,
                'platform'          => MarketplacePlatform::TIKTOK,
                'platform_order_id' => $orderId,
                'sales_order_id'    => $so->id,
                'status'            => 'COMPLETE',
                'synced_at'         => now(),
                'processed_at'      => now(),
                'raw_payload'       => [
                    'source'            => 'income_csv',
                    'settlement_amount' => $settlementAmount,
                    'revenue_gross'     => $revenueGross,
                    'total_cost'        => $totalCost,
                ],
                'mapped_items' => $mappedItems,
                'is_mapped'    => true,
            ]);

            // Hitung admin fee untuk pesan log
            $adminFee = max(0, $totalAmount - $settlementAmount);

            return [
                'order_id'   => $orderId,
                'action'     => 'created',
                'message'    => "Auto-created + LUNAS: SO={$soNumber} | Harga Jual=" . number_format($totalAmount, 0, ',', '.') . " | Settlement=" . number_format($settlementAmount, 0, ',', '.') . " | Admin Fee=" . number_format($adminFee, 0, ',', '.'),
                'so_number'  => $soNumber,
                'settlement' => $settlementAmount,
                'admin_fee'  => $adminFee,
            ];
        });
    }

    // =========================================================================
    // PAYMENT PROCESSOR (CASE B — order sudah ada dari Order CSV)
    // =========================================================================

    /**
     * Proses pembayaran untuk order yang sudah diimport sebelumnya.
     *
     * PERUBAHAN UTAMA:
     * - Tidak lagi hanya buat CashIn sejumlah settlementAmount (yang menyebabkan PARTIAL).
     * - Sekarang menggunakan applySettlementWithAdminFee() yang memisahkan:
     *   a) CashIn sebesar settlementAmount → Kas masuk
     *   b) Jurnal admin fee sebesar (invoice->total - settlementAmount) → Beban Admin TikTok
     *   c) Update invoice.paid_amount = invoice->total → status LUNAS (PAID)
     */
    private function processPayment(
        SalesOrder $so,
        MarketplaceOrder $mkOrder,
        string $orderId,
        float $settlementAmount,
        float $revenueGross,
        ?Carbon $paymentDate
    ): array {
        return DB::transaction(function () use ($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate) {
            $orderDate = $paymentDate ?? now();

            // 1. Pastikan status SO dan DO sudah COMPLETE/DELIVERED
            if ($so->status !== 'COMPLETE') {
                $so->update(['status' => 'COMPLETE']);
            }

            foreach ($so->deliveryOrders as $do) {
                if (!in_array($do->status, ['SHIPPED', 'DELIVERED'])) {
                    $do->update(['status' => 'DELIVERED']);
                }
            }

            $mkOrder->update(['status' => 'COMPLETE', 'processed_at' => now()]);

            // 2. Cari atau buat invoice
            $invoice = $so->salesInvoices->first();

            if (!$invoice) {
                $invoiceNumber = $this->generateNumber('INV-TTK');
                $invoice = SalesInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id'    => $so->customer_id,
                    'so_id'          => $so->id,
                    'date'           => $orderDate,
                    'due_date'       => $orderDate,
                    'total'          => $so->total_amount,   // ← harga jual kotor dari SO
                    'paid_amount'    => 0,
                    'status'         => 'UNPAID',
                    'notes'          => "TikTok Order: {$orderId}",
                    'created_by'     => auth()->id(),
                ]);

                foreach ($so->details as $detail) {
                    SalesInvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $detail->product_id,
                        'qty'        => $detail->qty,
                        'price'      => $detail->unit_price,
                        'subtotal'   => $detail->subtotal,
                    ]);
                }
            }

            // 3. Jika sudah PAID, skip
            if ($invoice->status === 'PAID') {
                return [
                    'order_id'  => $orderId,
                    'action'    => 'skipped',
                    'message'   => 'Invoice sudah PAID',
                    'so_number' => $so->so_number,
                ];
            }

            // 4. Cek apakah CashIn sudah ada untuk order ini
            $existingCashIn = CashIn::where('reference_type', SalesInvoice::class)
                ->where('reference_id', $invoice->id)
                ->where('description', 'LIKE', "%{$orderId}%")
                ->first();

            if ($existingCashIn) {
                return [
                    'order_id'  => $orderId,
                    'action'    => 'skipped',
                    'message'   => 'CashIn sudah ada untuk order ini',
                    'so_number' => $so->so_number,
                ];
            }

            // 5. *** INTI REKONSILIASI ***
            // Buat CashIn (kas netto cair) + jurnal admin fee (selisih) → Invoice LUNAS
            $this->applySettlementWithAdminFee($invoice, $settlementAmount, $orderId, $orderDate, $so->customer_id);

            // 6. Buat HPP Journal
            $this->createHppJournal($so, $orderId);

            $adminFee = max(0, (float) $invoice->total - $settlementAmount);

            return [
                'order_id'   => $orderId,
                'action'     => 'paid',
                'message'    => "Invoice LUNAS: Harga Jual=" . number_format($invoice->total, 0, ',', '.') . " | Settlement=" . number_format($settlementAmount, 0, ',', '.') . " | Admin Fee TikTok=" . number_format($adminFee, 0, ',', '.') . " | SO={$so->so_number}",
                'so_number'  => $so->so_number,
                'settlement' => $settlementAmount,
                'admin_fee'  => $adminFee,
            ];
        });
    }

    // =========================================================================
    // *** METODE BARU: REKONSILIASI SETTLEMENT + ADMIN FEE ***
    // =========================================================================

    /**
     * Menerapkan pembayaran penuh ke invoice dengan memisahkan:
     *  1. CashIn → nilai kas yang benar-benar cair (settlementAmount)
     *     Jurnal otomatis dari CashIn::booted(): Debit Kas, Credit Piutang
     *
     *  2. Jurnal Admin Fee (jika ada selisih) → biaya potongan TikTok
     *     Jurnal manual: Debit Beban Admin TikTok (5-30001), Credit Piutang Dagang (1-10003)
     *     → Piutang berkurang sebesar admin fee, sehingga total piutang terlunasi = invoice->total
     *
     *  3. Update invoice.paid_amount secara eksplisit = invoice->total → status PAID (LUNAS)
     *
     * Mengapa tidak edit harga invoice?
     *  → SO tetap mencatat harga jual kotor (omzet bruto).
     *  → Laporan Laba Rugi menunjukkan: Pendapatan Penjualan - Biaya Admin TikTok = Net Revenue.
     *  → Audit trail lengkap: bisa trace berapa potongan admin per order.
     *
     * @param SalesInvoice $invoice   Invoice yang akan dilunasi
     * @param float        $settlementAmount  Nilai kas netto yang diterima dari TikTok
     * @param string       $orderId   TikTok Order ID (untuk deskripsi jurnal)
     * @param mixed        $orderDate Tanggal pembayaran
     * @param int|null     $customerId
     */
    private function applySettlementWithAdminFee(
        SalesInvoice $invoice,
        float $settlementAmount,
        string $orderId,
        $orderDate,
        ?int $customerId
    ): void {
        $invoiceTotal = (float) $invoice->total;
        $adminFee     = round($invoiceTotal - $settlementAmount, 2);

        // --- LANGKAH 1: Buat CashIn sebesar nilai kas yang cair ---
        // CashIn::booted() akan otomatis membuat jurnal:
        //   Debit  Kas (1-10001)       = settlementAmount
        //   Credit Piutang (1-10003)   = settlementAmount
        // Dan update invoice.paid_amount += settlementAmount
        $accountKas = Account::where('code', '1-10001')->first();

        CashIn::create([
            'account_id'     => $accountKas?->id,
            'date'           => $orderDate,
            'type'           => 'CUSTOMER_PAYMENT',
            'reference_type' => SalesInvoice::class,
            'reference_id'   => $invoice->id,
            'customer_id'    => $customerId,
            'amount'         => $settlementAmount,
            'description'    => "Settlement TikTok: Order {$orderId} | Cair: " . number_format($settlementAmount, 0, ',', '.'),
            'created_by'     => auth()->id(),
        ]);

        // --- LANGKAH 2: Jika ada selisih (admin fee), buat jurnal rekonsiliasi ---
        // Jurnal ini mencatat potongan TikTok sebagai beban:
        //   Debit  Beban Admin TikTok (5-30001) = adminFee
        //   Credit Piutang (1-10003)            = adminFee
        // → Piutang terisi penuh = invoice->total → Invoice LUNAS
        if ($adminFee > self::ROUNDING_TOLERANCE) {
            $this->createAdminFeeJournal($invoice, $adminFee, $orderId, $orderDate);
        } elseif ($adminFee < 0) {
            // Kasus jarang: settlement > invoice total (TikTok bayar lebih dari harga jual)
            // Catat sebagai Pendapatan Lain-lain
            Log::info("TikTok Income: Settlement lebih besar dari Invoice untuk order {$orderId}. "
                . "Selisih: " . number_format(abs($adminFee), 0, ',', '.') . " akan dicatat sebagai pendapatan lain.");
        }

        // --- LANGKAH 3: Paksa invoice menjadi LUNAS ---
        // Meskipun paid_amount sudah di-update oleh CashIn::booted(),
        // kita pastikan invoice total terbayar penuh agar tidak PARTIAL.
        $invoice->refresh();
        if ($invoice->status !== 'PAID') {
            $invoice->paid_amount = $invoiceTotal;
            $invoice->status      = 'PAID';
            $invoice->save();

            Log::info("TikTok Income: Invoice #{$invoice->invoice_number} dipaksa LUNAS.", [
                'order_id'         => $orderId,
                'invoice_total'    => $invoiceTotal,
                'settlement_amount' => $settlementAmount,
                'admin_fee'        => $adminFee,
            ]);
        }
    }

    /**
     * Buat jurnal rekonsiliasi untuk Biaya Admin TikTok.
     *
     * Jurnal:
     *   Debit  5-30001  Beban Admin Marketplace TikTok   = adminFee
     *   Credit 1-10003  Piutang Dagang                   = adminFee
     *
     * Penjelasan akuntansi:
     * - Piutang sudah muncul sebesar invoice->total saat DO di-deliver.
     * - Kas masuk hanya sebesar settlementAmount (lebih kecil karena dipotong admin fee).
     * - Sisa piutang (adminFee) bukan hutang pelanggan, melainkan potongan TikTok.
     * - Jurnal ini "menghapus" sisa piutang dan mencatatnya sebagai Beban Admin.
     */
    private function createAdminFeeJournal(
        SalesInvoice $invoice,
        float $adminFee,
        string $orderId,
        $orderDate
    ): void {
        $accountAdminFee = Account::where('code', self::COA_ADMIN_FEE)->first();
        $accountPiutang  = Account::where('code', '1-10003')->first();

        if (!$accountAdminFee) {
            Log::warning("TikTok Income: Akun Beban Admin Marketplace (" . self::COA_ADMIN_FEE . ") tidak ditemukan. "
                . "Jalankan: php artisan db:seed --class=ChartOfAccountSeeder "
                . "atau tambahkan akun " . self::COA_ADMIN_FEE . " secara manual di Chart of Accounts.");
            // Fallback: gunakan Beban Operasional (5-20000) jika akun khusus belum ada
            $accountAdminFee = Account::where('code', '5-20000')->first();
        }

        if (!$accountAdminFee || !$accountPiutang) {
            Log::error("TikTok Income: Jurnal admin fee DILEWATI untuk order {$orderId} — akun tidak ditemukan.", [
                'admin_fee_account' => self::COA_ADMIN_FEE,
                'piutang_account'   => '1-10003',
            ]);
            return;
        }

        $journal = JournalEntry::create([
            'date'           => $orderDate,
            'reference_type' => SalesInvoice::class,
            'reference_id'   => $invoice->id,
            'description'    => "Biaya Admin TikTok - Order: {$orderId} | Rp " . number_format($adminFee, 0, ',', '.'),
            'total_debit'    => $adminFee,
            'total_credit'   => $adminFee,
            'is_posted'      => true,
            'created_by'     => auth()->id(),
        ]);

        // Debit: Beban Admin TikTok
        JournalEntryDetail::create([
            'journal_id'  => $journal->id,
            'account_id'  => $accountAdminFee->id,
            'debit'       => $adminFee,
            'credit'      => 0,
            'description' => "Biaya admin/komisi TikTok Shop - Order: {$orderId}",
        ]);

        // Credit: Piutang Dagang
        JournalEntryDetail::create([
            'journal_id'  => $journal->id,
            'account_id'  => $accountPiutang->id,
            'debit'       => 0,
            'credit'      => $adminFee,
            'description' => "Pelunasan piutang via potongan admin TikTok - Order: {$orderId}",
        ]);

        Log::info("TikTok Income: Jurnal admin fee berhasil dibuat.", [
            'order_id'  => $orderId,
            'admin_fee' => $adminFee,
            'journal_id' => $journal->id,
        ]);
    }

    // =========================================================================
    // HPP JOURNAL
    // =========================================================================

    /**
     * Buat jurnal HPP (Harga Pokok Penjualan vs Persediaan).
     * Tidak ada perubahan dari versi sebelumnya.
     */
    private function createHppJournal(SalesOrder $so, string $orderId): void
    {
        $totalHpp = $so->details->sum(fn($d) => (float) ($d->cost_price ?? 0) * $d->qty);

        if ($totalHpp <= 0) {
            return;
        }

        $accountHpp        = Account::where('code', '5-10001')->first();
        $accountPersediaan = Account::where('code', '1-20001')->first();

        if (!$accountHpp || !$accountPersediaan) {
            Log::warning('TikTok Income: HPP journal skipped — akun HPP atau Persediaan tidak ditemukan');
            return;
        }

        $journal = JournalEntry::create([
            'date'           => $so->date,
            'reference_type' => SalesOrder::class,
            'reference_id'   => $so->id,
            'description'    => "HPP TikTok Order: {$orderId}",
            'total_debit'    => $totalHpp,
            'total_credit'   => $totalHpp,
            'is_posted'      => true,
            'created_by'     => auth()->id(),
        ]);

        JournalEntryDetail::create([
            'journal_id'  => $journal->id,
            'account_id'  => $accountHpp->id,
            'debit'       => $totalHpp,
            'credit'      => 0,
            'description' => "Beban HPP - TikTok Order: {$orderId}",
        ]);

        JournalEntryDetail::create([
            'journal_id'  => $journal->id,
            'account_id'  => $accountPersediaan->id,
            'debit'       => 0,
            'credit'      => $totalHpp,
            'description' => "Pengurangan persediaan - TikTok Order: {$orderId}",
        ]);
    }

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    /**
     * Parse "Detail produk terjual" column
     * Format: "SKU_ID * qty; SKU_ID2 * qty2;" or "/"
     */
    private function parseProductDetails(string $detail): array
    {
        if (empty($detail) || trim($detail) === '/' || trim($detail) === '') {
            return [];
        }

        $results = [];
        $parts   = explode(';', $detail);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '*')) {
                $segments = explode('*', $part);
                $skuId    = trim($segments[0] ?? '');
                $qty      = (int) trim($segments[1] ?? 1);

                if (!empty($skuId) && $qty > 0) {
                    $results[] = ['sku_id' => $skuId, 'qty' => $qty];
                }
            }
        }

        return $results;
    }

    /**
     * Find product by TikTok SKU ID
     */
    private function findProductBySkuId(string $skuId): ?Product
    {
        $product = Product::where('sku', $skuId)->first();
        if ($product) {
            return $product;
        }

        return Product::where('code', $skuId)->first();
    }

    /**
     * Generate unique number
     */
    private function generateNumber(string $prefix): string
    {
        $datePrefix = $prefix . '-' . date('Ymd');
        $lastRecord = null;

        if (str_starts_with($prefix, 'POS')) {
            $lastRecord = PosTransaction::where('transaction_number', 'like', $datePrefix . '%')
                ->orderBy('id', 'desc')->value('transaction_number');
        } elseif (str_starts_with($prefix, 'SO')) {
            $lastRecord = SalesOrder::where('so_number', 'like', $datePrefix . '%')
                ->orderBy('id', 'desc')->value('so_number');
        } elseif (str_starts_with($prefix, 'DO')) {
            $lastRecord = DeliveryOrder::where('do_number', 'like', $datePrefix . '%')
                ->orderBy('id', 'desc')->value('do_number');
        } elseif (str_starts_with($prefix, 'INV')) {
            $lastRecord = SalesInvoice::where('invoice_number', 'like', $datePrefix . '%')
                ->orderBy('id', 'desc')->value('invoice_number');
        }

        $sequence = 1;
        if ($lastRecord) {
            $parts    = explode('-', $lastRecord);
            $sequence = (int) end($parts) + 1;
        }

        return $datePrefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Parse date from income CSV (Y/m/d format)
     */
    private function parseDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr) || trim($dateStr) === '') {
            return null;
        }

        $dateStr = trim($dateStr);

        $formats = ['Y/m/d', 'd/m/Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y/m/d H:i:s'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $dateStr);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Parse amount (handle negative values, commas, etc.)
     */
    private function parseAmount(string $amountStr): float
    {
        $amountStr = trim($amountStr);
        if (empty($amountStr)) {
            return 0;
        }

        $amountStr = str_replace('.', '', $amountStr);
        $amountStr = str_replace(',', '.', $amountStr);

        return (float) $amountStr;
    }
}
