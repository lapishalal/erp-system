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
     * Main entry point — import TikTok income CSV
     */
    public function importIncome(string $filePath): array
    {
        $rows = $this->parseCsv($filePath);

        $results = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => [],
        ];

        // Filter only "Pesanan" type and TikTok Shop source
        $orderRows = array_filter($rows, function ($row) {
            $type = trim($row['Jenis transaksi'] ?? '');
            $source = trim($row['Sumber pesanan'] ?? '');
            return $type === 'Pesanan' && $source === 'TikTok Shop';
        });

        $results['total'] = count($orderRows);

        // Group by Order ID (settlement rows are 1 per order in income CSV)
        foreach ($orderRows as $row) {
            try {
                $result = $this->processIncomeRow($row);

                match ($result['action']) {
                    'created', 'paid' => $results['created']++,
                    'updated' => $results['updated']++,
                    'skipped' => $results['skipped']++,
                    default => null,
                };

                $results['details'][] = $result;
            } catch (\Throwable $e) {
                $results['errors']++;
                $results['details'][] = [
                    'order_id' => $row['ID Pesanan/Penyesuaian'] ?? 'unknown',
                    'action' => 'error',
                    'message' => $e->getMessage(),
                ];
                Log::error('TikTok Income Import Error', [
                    'row' => $row['ID Pesanan/Penyesuaian'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Parse file (CSV or XLSX) into array of rows
     */
    private function parseCsv(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'xlsx' || $extension === 'xls') {
            return $this->parseExcel($filePath);
        }

        return $this->parseCsvFile($filePath);
    }

    /**
     * Parse XLSX/XLS file using Maatwebsite Excel
     */
    private function parseExcel(string $filePath): array
    {
        $rows = [];
        $allRows = Excel::toArray(new \stdClass, $filePath);

        if (empty($allRows) || empty($allRows[0])) {
            throw new \Exception('File Excel income kosong');
        }

        $header = array_map(fn($h) => trim((string) $h), $allRows[0][0] ?? []);

        for ($i = 1; $i < count($allRows[0]); $i++) {
            $row = $allRows[0][$i];
            $values = array_map(fn($v) => trim((string) ($v ?? '')), $row);

            if (count($values) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $values);
        }

        return $rows;
    }

    /**
     * Parse CSV file using native PHP
     */
    private function parseCsvFile(string $filePath): array
    {
        $rows = [];
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

    /**
     * Process a single income row
     */
    private function processIncomeRow(array $row): array
    {
        $orderId = trim($row['ID Pesanan/Penyesuaian'] ?? '');
        $settlementAmount = $this->parseAmount($row['Jumlah penyelesaian pembayaran'] ?? '0');
        $revenueGross = $this->parseAmount($row['Total Pendapatan'] ?? '0');
        $totalCost = $this->parseAmount($row['Total Biaya'] ?? '0');
        $paymentDate = $this->parseDate($row['Waktu pembayaran pesanan'] ?? '');

        if (empty($orderId)) {
            return ['order_id' => 'unknown', 'action' => 'skipped', 'message' => 'Order ID kosong'];
        }

        // Find existing marketplace order
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
            // Order exists but chain not yet created (items were unmapped during order import)
            return $this->handlePendingOrder($mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
        }

        return $this->processPayment($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
    }

    /**
     * Handle income for order that was imported from Order CSV but chain not yet created
     * (items were unmapped). If user has since mapped all items, auto-create chain + process payment.
     */
    private function handlePendingOrder(MarketplaceOrder $mkOrder, string $orderId, float $settlementAmount, float $revenueGross, ?Carbon $paymentDate): array
    {
        $mkOrder->load('items');

        // Check if all items are now mapped
        $totalItems = $mkOrder->items->count();
        $mappedItems = $mkOrder->items->where('is_mapped', true)->count();

        if ($totalItems === 0 || $mappedItems < $totalItems) {
            $unmappedCount = $totalItems - $mappedItems;
            return [
                'order_id' => $orderId,
                'action' => 'skipped',
                'message' => "Order ada tapi {$unmappedCount} item belum di-map. Silakan map di halaman 'Produk Belum Ter-map TikTok' terlebih dahulu, lalu import ulang income.",
            ];
        }

        // All items are mapped → auto-create chain, then process payment
        return DB::transaction(function () use ($mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate) {
            // 1. Create order chain
            $processingService = new TikTokOrderProcessingService();
            $chainResult = $processingService->createOrderChain($mkOrder, 'DELIVERED');

            if (($chainResult['action'] ?? '') === 'skipped') {
                return [
                    'order_id' => $orderId,
                    'action' => 'skipped',
                    'message' => 'Chain sudah ada: ' . ($chainResult['message'] ?? ''),
                ];
            }

            // 2. Reload and process payment
            $mkOrder->refresh();
            $so = $mkOrder->salesOrder;

            if (!$so) {
                return [
                    'order_id' => $orderId,
                    'action' => 'error',
                    'message' => 'Gagal membuat chain untuk order ini',
                ];
            }

            return $this->processPayment($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate);
        });
    }

    /**
     * Handle income for order that was never imported from Order CSV
     * Create full chain + process payment in one go
     */
    private function handleMissingOrder(string $orderId, array $row, float $settlementAmount, float $revenueGross, ?Carbon $paymentDate): array
    {
        return DB::transaction(function () use ($orderId, $row, $settlementAmount, $revenueGross, $paymentDate) {
            $customer = (new TikTokCsvImportService())->getOrCreateTikTokCustomer();
            $warehouse = (new TikTokCsvImportService())->getFirstActiveWarehouse();

            // Parse product info from income CSV's "Detail produk terjual" column
            // Format: "SKU_ID * qty; SKU_ID2 * qty2;" or "/"
            $productDetails = $this->parseProductDetails($row['Detail produk terjual'] ?? '');

            if (empty($productDetails)) {
                return [
                    'order_id' => $orderId,
                    'action' => 'skipped',
                    'message' => 'Detail produk tidak tersedia (/), skip auto-create. Import dari Order CSV terlebih dahulu.',
                ];
            }

            // Map products and build items
            $mappedItems = [];
            $totalQty = 0;
            $totalAmount = 0;
            $totalCost = 0;

            foreach ($productDetails as $pd) {
                $product = $this->findProductBySkuId($pd['sku_id']);

                if (!$product) {
                    // Try to find by Seller SKU
                    $product = Product::where('sku', $pd['sku_id'])->first();
                }

                if ($product) {
                    $costPrice = $product->getHpp();
                    // For income-only imports, unit price is estimated from gross revenue / qty
                    $unitPrice = count($productDetails) === 1
                        ? $revenueGross / $pd['qty']
                        : (float) $product->default_sale_price;

                    $mappedItems[] = [
                        'product' => $product,
                        'qty' => $pd['qty'],
                        'unit_price' => round($unitPrice, 2),
                        'cost_price' => $costPrice,
                        'subtotal' => round($unitPrice * $pd['qty'], 2),
                    ];
                    $totalQty += $pd['qty'];
                    $totalAmount += round($unitPrice * $pd['qty'], 2);
                    $totalCost += $costPrice * $pd['qty'];
                }
            }

            if (empty($mappedItems)) {
                return [
                    'order_id' => $orderId,
                    'action' => 'skipped',
                    'message' => 'Produk tidak ditemukan di database. Pastikan Seller SKU sudah diisi.',
                ];
            }

            $orderDate = $paymentDate ?? now();
            $paymentMethod = 'TRANSFER'; // Default for income (already settled)

            // 1. Create POS Transaction
            $posNumber = $this->generateNumber('POS-TTK');
            $pos = PosTransaction::create([
                'transaction_number' => $posNumber,
                'date' => $orderDate,
                'customer_id' => $customer->id,
                'subtotal' => $totalAmount,
                'discount' => 0,
                'tax' => 0,
                'total' => $totalAmount,
                'paid_amount' => $settlementAmount,
                'change_amount' => 0,
                'payment_method' => $paymentMethod,
                'created_by' => auth()->id(),
            ]);

            foreach ($mappedItems as $item) {
                PosTransactionDetail::create([
                    'pos_transaction_id' => $pos->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // 2. Create Sales Order (COMPLETE — income means settled)
            $soNumber = $this->generateNumber('SO-TTK');
            $so = SalesOrder::create([
                'so_number' => $soNumber,
                'date' => $orderDate,
                'customer_id' => $customer->id,
                'status' => 'COMPLETE',
                'source' => 'tiktok',
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $totalAmount - $totalCost,
                'notes' => "TikTok Order: {$orderId} (from income import)",
                'created_by' => auth()->id(),
            ]);

            $soDetails = [];
            foreach ($mappedItems as $item) {
                $detail = SalesOrderDetail::create([
                    'so_id' => $so->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'delivered_qty' => $item['qty'],
                    'remaining_qty' => 0,
                    'subtotal' => $item['subtotal'],
                    'profit' => $item['subtotal'] - ($item['cost_price'] * $item['qty']),
                ]);
                $soDetails[] = $detail;
            }

            // 3. Create DO (DRAFT → DELIVERED to trigger stock deduction)
            $doNumber = $this->generateNumber('DO-TTK');
            $do = DeliveryOrder::create([
                'do_number' => $doNumber,
                'so_id' => $so->id,
                'date' => $orderDate,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse?->id,
                'status' => 'DRAFT',
                'total_qty' => $totalQty,
                'notes' => "TikTok Order: {$orderId} (auto from income)",
                'created_by' => auth()->id(),
            ]);

            foreach ($soDetails as $soDetail) {
                DeliveryOrderDetail::create([
                    'do_id' => $do->id,
                    'so_detail_id' => $soDetail->id,
                    'product_id' => $soDetail->product_id,
                    'qty' => $soDetail->qty,
                ]);
            }

            // Trigger stock deduction
            $do->update(['status' => 'DELIVERED']);

            // 4. Create Invoice
            $invoiceNumber = $this->generateNumber('INV-TTK');
            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'so_id' => $so->id,
                'date' => $orderDate,
                'due_date' => $orderDate,
                'total' => $totalAmount,
                'paid_amount' => 0,
                'status' => 'UNPAID',
                'notes' => "TikTok Order: {$orderId}",
                'created_by' => auth()->id(),
            ]);

            foreach ($mappedItems as $item) {
                SalesInvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // 5. Create CashIn (triggers auto-journal via CashIn::booted)
            $accountKas = Account::where('code', '1-10001')->first();

            CashIn::create([
                'account_id' => $accountKas?->id,
                'date' => $orderDate,
                'type' => 'CUSTOMER_PAYMENT',
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'customer_id' => $customer->id,
                'amount' => $settlementAmount,
                'description' => "Settlement TikTok: Order {$orderId} | Netto: " . number_format($settlementAmount, 0, ',', '.'),
                'created_by' => auth()->id(),
            ]);

            // 6. Create HPP Journal (separate from CashIn auto-journal)
            $this->createHppJournal($so, $orderId);

            // 7. Save to marketplace_orders
            $tenantId = auth()->user()->tenant_id ?? null;
            MarketplaceOrder::create([
                'tenant_id' => $tenantId,
                'connection_id' => null,
                'platform' => MarketplacePlatform::TIKTOK,
                'platform_order_id' => $orderId,
                'sales_order_id' => $so->id,
                'status' => 'COMPLETE',
                'synced_at' => now(),
                'processed_at' => now(),
                'raw_payload' => [
                    'source' => 'income_csv',
                    'settlement_amount' => $settlementAmount,
                    'revenue_gross' => $revenueGross,
                    'total_cost' => $totalCost,
                ],
                'mapped_items' => $mappedItems,
                'is_mapped' => true,
            ]);

            return [
                'order_id' => $orderId,
                'action' => 'created',
                'message' => "Auto-created + paid: SO={$soNumber}, Settlement=" . number_format($settlementAmount, 0, ',', '.'),
                'so_number' => $soNumber,
                'settlement' => $settlementAmount,
            ];
        });
    }

    /**
     * Process payment for existing order (from Order CSV import)
     */
    private function processPayment(SalesOrder $so, MarketplaceOrder $mkOrder, string $orderId, float $settlementAmount, float $revenueGross, ?Carbon $paymentDate): array
    {
        return DB::transaction(function () use ($so, $mkOrder, $orderId, $settlementAmount, $revenueGross, $paymentDate) {
            $orderDate = $paymentDate ?? now();

            // 1. Fix logistics status if still pending
            if ($so->status !== 'COMPLETE') {
                $so->update(['status' => 'COMPLETE']);
            }

            foreach ($so->deliveryOrders as $do) {
                if (!in_array($do->status, ['SHIPPED', 'DELIVERED'])) {
                    $do->update(['status' => 'DELIVERED']); // triggers stock deduction
                }
            }

            $mkOrder->update(['status' => 'COMPLETE', 'processed_at' => now()]);

            // 2. Find or check invoice
            $invoice = $so->salesInvoices->first();

            if (!$invoice) {
                // Create invoice if missing
                $invoiceNumber = $this->generateNumber('INV-TTK');
                $invoice = SalesInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $so->customer_id,
                    'so_id' => $so->id,
                    'date' => $orderDate,
                    'due_date' => $orderDate,
                    'total' => $so->total_amount,
                    'paid_amount' => 0,
                    'status' => 'UNPAID',
                    'notes' => "TikTok Order: {$orderId}",
                    'created_by' => auth()->id(),
                ]);

                foreach ($so->details as $detail) {
                    SalesInvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $detail->product_id,
                        'qty' => $detail->qty,
                        'price' => $detail->unit_price,
                        'subtotal' => $detail->subtotal,
                    ]);
                }
            }

            // 3. Check if already paid
            if ($invoice->status === 'PAID') {
                return [
                    'order_id' => $orderId,
                    'action' => 'skipped',
                    'message' => 'Invoice sudah PAID',
                    'so_number' => $so->so_number,
                ];
            }

            // 4. Check if CashIn already exists for this invoice
            $existingCashIn = CashIn::where('reference_type', SalesInvoice::class)
                ->where('reference_id', $invoice->id)
                ->where('description', 'LIKE', "%{$orderId}%")
                ->first();

            if ($existingCashIn) {
                return [
                    'order_id' => $orderId,
                    'action' => 'skipped',
                    'message' => 'CashIn sudah ada untuk order ini',
                    'so_number' => $so->so_number,
                ];
            }

            // 5. Create CashIn → auto-journal via CashIn::booted()
            $accountKas = Account::where('code', '1-10001')->first();

            CashIn::create([
                'account_id' => $accountKas?->id,
                'date' => $orderDate,
                'type' => 'CUSTOMER_PAYMENT',
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'customer_id' => $so->customer_id,
                'amount' => $settlementAmount,
                'description' => "Settlement TikTok: Order {$orderId} | Netto: " . number_format($settlementAmount, 0, ',', '.'),
                'created_by' => auth()->id(),
            ]);
            // CashIn::booted() auto-creates:
            //   Journal: Debit Kas (1-10001), Credit Penjualan (4-10001)
            //   Updates: invoice.paid_amount → status PAID

            // 6. Create HPP Journal (separate)
            $this->createHppJournal($so, $orderId);

            return [
                'order_id' => $orderId,
                'action' => 'paid',
                'message' => "Settlement diproses: " . number_format($settlementAmount, 0, ',', '.') . " | SO={$so->so_number}",
                'so_number' => $so->so_number,
                'settlement' => $settlementAmount,
            ];
        });
    }

    /**
     * Create HPP journal entry (HPP vs Persediaan)
     */
    private function createHppJournal(SalesOrder $so, string $orderId): void
    {
        $totalHpp = $so->details->sum(fn($d) => (float) ($d->cost_price ?? 0) * $d->qty);

        if ($totalHpp <= 0) {
            return;
        }

        $accountHpp = Account::where('code', '5-10001')->first();
        $accountPersediaan = Account::where('code', '1-20001')->first();

        if (!$accountHpp || !$accountPersediaan) {
            Log::warning('TikTok Income: HPP journal skipped — akun HPP atau Persediaan tidak ditemukan');
            return;
        }

        $journal = JournalEntry::create([
            'date' => $so->date,
            'reference_type' => SalesOrder::class,
            'reference_id' => $so->id,
            'description' => "HPP TikTok Order: {$orderId}",
            'total_debit' => $totalHpp,
            'total_credit' => $totalHpp,
            'is_posted' => true,
            'created_by' => auth()->id(),
        ]);

        JournalEntryDetail::create([
            'journal_id' => $journal->id,
            'account_id' => $accountHpp->id,
            'debit' => $totalHpp,
            'credit' => 0,
            'description' => "Beban HPP - TikTok Order: {$orderId}",
        ]);

        JournalEntryDetail::create([
            'journal_id' => $journal->id,
            'account_id' => $accountPersediaan->id,
            'debit' => 0,
            'credit' => $totalHpp,
            'description' => "Pengurangan persediaan - TikTok Order: {$orderId}",
        ]);
    }

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
        $parts = explode(';', $detail);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Format: "1735979040413353114 * 1"
            if (str_contains($part, '*')) {
                $segments = explode('*', $part);
                $skuId = trim($segments[0] ?? '');
                $qty = (int) trim($segments[1] ?? 1);

                if (!empty($skuId) && $qty > 0) {
                    $results[] = ['sku_id' => $skuId, 'qty' => $qty];
                }
            }
        }

        return $results;
    }

    /**
     * Find product by TikTok SKU ID (tries Seller SKU field first, then falls back)
     */
    private function findProductBySkuId(string $skuId): ?Product
    {
        // Try to match by sku field (Seller SKU)
        $product = Product::where('sku', $skuId)->first();
        if ($product) {
            return $product;
        }

        // Try to match by code (internal SKU)
        $product = Product::where('code', $skuId)->first();
        if ($product) {
            return $product;
        }

        return null;
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
            $parts = explode('-', $lastRecord);
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

        // Remove thousand separators, handle negatives
        $amountStr = str_replace('.', '', $amountStr);
        $amountStr = str_replace(',', '.', $amountStr);

        return (float) $amountStr;
    }
}