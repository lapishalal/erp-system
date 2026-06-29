<?php

namespace App\Services;

use App\Enums\MarketplacePlatform;
use App\Models\Account;
use App\Models\CashIn;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
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

class TikTokCsvImportService
{
    /**
     * Main entry point — import TikTok order CSV
     */
    public function importOrders(string $filePath): array
    {
        $rows = $this->parseCsv($filePath);
        $orders = $this->groupByOrderId($rows);

        $customer = $this->getOrCreateTikTokCustomer();
        $warehouse = $this->getFirstActiveWarehouse();

        $results = [
            'total' => count($orders),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'unmapped' => 0,
            'details' => [],
        ];

        foreach ($orders as $orderId => $orderData) {
            try {
                $result = $this->processOrder($orderId, $orderData, $customer, $warehouse);

                match ($result['action']) {
                    'created' => $results['created']++,
                    'updated' => $results['updated']++,
                    'skipped' => $results['skipped']++,
                    'unmapped' => $results['unmapped']++,
                    default => null,
                };

                $results['details'][] = $result;
            } catch (\Throwable $e) {
                $results['errors']++;
                $results['details'][] = [
                    'order_id' => $orderId,
                    'action' => 'error',
                    'message' => $e->getMessage(),
                ];
                Log::error('TikTok CSV Import Error', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Parse CSV file into array of rows
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
            throw new \Exception('File Excel kosong');
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
            throw new \Exception('File CSV tidak bisa dibaca: ' . $filePath);
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \Exception('File CSV kosong atau header tidak ditemukan');
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
     * Group CSV rows by Order ID (one order can have multiple product rows)
     */
    private function groupByOrderId(array $rows): array
    {
        $orders = [];

        foreach ($rows as $row) {
            $orderId = $row['Order ID'] ?? '';
            if (empty($orderId)) {
                continue;
            }

            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $orderId,
                    'status' => $row['Order Status'] ?? '',
                    'substatus' => $row['Order Substatus'] ?? '',
                    'created_time' => $this->parseDate($row['Created Time'] ?? ''),
                    'paid_time' => $this->parseDate($row['Paid Time'] ?? ''),
                    'shipped_time' => $this->parseDate($row['Shipped Time'] ?? ''),
                    'delivered_time' => $this->parseDate($row['Delivered Time'] ?? ''),
                    'cancelled_time' => $this->parseDate($row['Cancelled Time'] ?? ''),
                    'cancel_reason' => $row['Cancel Reason'] ?? '',
                    'payment_method' => $row['Payment Method'] ?? '',
                    'order_amount' => (float) ($row['Order Amount'] ?? 0),
                    'tracking_id' => $row['Tracking ID'] ?? '',
                    'package_id' => $row['Package ID'] ?? '',
                    'shipping_provider' => $row['Shipping Provider Name'] ?? '',
                    'items' => [],
                ];
            }

            // Add item (one row per product variation)
            $sellerSku = $row['Seller SKU'] ?? '';
            $skuId = $row['SKU ID'] ?? '';
            $productName = $row['Product Name'] ?? '';
            $variation = $row['Variation'] ?? '';
            $quantity = (int) ($row['Quantity'] ?? 0);
            $subtotalAfterDiscount = (float) ($row['SKU Subtotal After Discount'] ?? 0);

            if ($quantity > 0) {
                $orders[$orderId]['items'][] = [
                    'sku_id' => $skuId,
                    'seller_sku' => $sellerSku,
                    'product_name' => $productName,
                    'variation' => $variation,
                    'quantity' => $quantity,
                    'subtotal_after_discount' => $subtotalAfterDiscount,
                    'unit_price' => $quantity > 0 ? round($subtotalAfterDiscount / $quantity, 2) : 0,
                ];
            }
        }

        return $orders;
    }

    /**
     * Process a single order (new or existing)
     */
    private function processOrder(string $orderId, array $orderData, Customer $customer, ?Warehouse $warehouse): array
    {
        $statusMapping = $this->mapTikTokStatus($orderData['status']);
        $erpStatus = $statusMapping['erp_status'];

        // Check if already imported
        $mkOrder = MarketplaceOrder::where('platform', 'tiktok')
            ->where('platform_order_id', $orderId)
            ->first();

        if ($mkOrder) {
            return $this->processExistingOrder($mkOrder, $orderData, $erpStatus);
        }

        // Skip cancelled orders entirely
        if ($erpStatus === 'CANCEL') {
            return [
                'order_id' => $orderId,
                'action' => 'skipped',
                'message' => 'Order dibatalkan, tidak diproses',
            ];
        }

        return $this->createNewOrder($orderId, $orderData, $erpStatus, $customer, $warehouse);
    }

    /**
     * Process re-imported order (status may have changed)
     */
    private function processExistingOrder(MarketplaceOrder $mkOrder, array $orderData, string $erpStatus): array
    {
        $so = $mkOrder->salesOrder;
        if (!$so) {
            // SO was deleted somehow, recreate
            $mkOrder->delete();
            return $this->createNewOrder(
                $orderData['order_id'],
                $orderData,
                $erpStatus,
                $this->getOrCreateTikTokCustomer(),
                $this->getFirstActiveWarehouse()
            );
        }

        $previousStatus = $mkOrder->status;

        // No change
        if ($previousStatus === $erpStatus) {
            return [
                'order_id' => $orderData['order_id'],
                'action' => 'skipped',
                'message' => 'Status sama, tidak ada perubahan',
            ];
        }

        // Order was cancelled
        if ($erpStatus === 'CANCEL') {
            $so->update(['status' => 'CANCEL']);
            foreach ($so->deliveryOrders as $do) {
                if ($do->status !== 'CANCEL') {
                    $do->update(['status' => 'CANCEL']);
                }
            }
            $mkOrder->update(['status' => 'CANCEL', 'processed_at' => now()]);

            return [
                'order_id' => $orderData['order_id'],
                'action' => 'updated',
                'message' => 'Order dibatalkan',
            ];
        }

        // Upgrade status
        $doStatus = $this->getDoStatus($erpStatus);

        // Update SO status if needed
        if ($so->status !== $erpStatus) {
            $so->updateQuietly(['status' => $erpStatus]);
        }

        // Update DO status (triggers stock deduction via model events)
        foreach ($so->deliveryOrders as $do) {
            if (!in_array($do->status, ['SHIPPED', 'DELIVERED']) && in_array($doStatus, ['SHIPPED', 'DELIVERED'])) {
                $do->update(['status' => $doStatus]);
            }
        }

        $mkOrder->update(['status' => $erpStatus, 'processed_at' => now()]);

        return [
            'order_id' => $orderData['order_id'],
            'action' => 'updated',
            'message' => "Status diupdate: {$previousStatus} → {$erpStatus}",
            'so_number' => $so->so_number,
        ];
    }

    /**
     * Create brand new order: save items to marketplace_order_items, then either
     * create full chain (all mapped) or save as pending (has unmapped items).
     */
    private function createNewOrder(string $orderId, array $orderData, string $erpStatus, Customer $customer, ?Warehouse $warehouse): array
    {
        return DB::transaction(function () use ($orderId, $orderData, $erpStatus, $customer, $warehouse) {
            $tenantId = auth()->user()->tenant_id ?? null;

            // 1. Create marketplace_orders record FIRST (for idempotency tracking)
            $mkOrder = MarketplaceOrder::create([
                'tenant_id' => $tenantId,
                'connection_id' => null,
                'platform' => MarketplacePlatform::TIKTOK,
                'platform_order_id' => $orderId,
                'platform_order_sn' => $orderData['package_id'] ?? null,
                'sales_order_id' => null, // will be set after chain creation
                'status' => $erpStatus,
                'synced_at' => now(),
                'processed_at' => null,
                'raw_payload' => $orderData,
                'mapped_items' => null,
                'is_mapped' => false,
                'error_message' => null,
            ]);

            // 2. Save ALL items to marketplace_order_items (mapped + unmapped)
            $mappedCount = 0;
            $unmappedCount = 0;

            foreach ($orderData['items'] as $item) {
                $product = $this->findProduct($item);

                MarketplaceOrderItem::create([
                    'tenant_id' => $tenantId,
                    'marketplace_order_id' => $mkOrder->id,
                    'platform_sku_id' => $item['sku_id'] ?? '',
                    'seller_sku' => $item['seller_sku'] ?? '',
                    'product_name' => $item['product_name'] ?? '',
                    'variation' => $item['variation'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal_after_discount' => $item['subtotal_after_discount'],
                    'mapped_product_id' => $product?->id,
                    'is_mapped' => !is_null($product),
                ]);

                if ($product) {
                    $mappedCount++;
                } else {
                    $unmappedCount++;
                }
            }

            // 3. If ALL items are mapped → create full chain immediately via TikTokOrderProcessingService
            if ($unmappedCount === 0 && $mappedCount > 0) {
                $processingService = new TikTokOrderProcessingService();
                $chainResult = $processingService->createOrderChain($mkOrder);

                return [
                    'order_id' => $orderId,
                    'action' => $chainResult['action'],
                    'message' => 'Berhasil: ' . $chainResult['message'],
                    'so_number' => $chainResult['so_number'] ?? null,
                    'do_status' => $chainResult['do_status'] ?? null,
                ];
            }

            // 4. Has unmapped items → save as pending, user needs to map manually
            $unmappedNames = collect($orderData['items'])
                ->filter(fn($i) => !$this->findProduct($i))
                ->map(fn($i) => ($i['product_name'] ?? 'unknown') . ($i['variation'] ? ' (' . $i['variation'] . ')' : ''))
                ->join(', ');

            $mkOrder->update([
                'is_mapped' => false,
                'error_message' => $unmappedCount . ' item belum ter-map: ' . $unmappedNames,
            ]);

            return [
                'order_id' => $orderId,
                'action' => 'unmapped',
                'message' => "{$unmappedCount} dari " . ($mappedCount + $unmappedCount) . " item belum ter-map. Silakan map di halaman 'Produk Belum Ter-map TikTok'.",
                'unmapped_count' => $unmappedCount,
                'mapped_count' => $mappedCount,
            ];
        });
    }

    /**
     * Find product by Seller SKU (primary) or Product Name + Variation (fallback)
     */
    private function findProduct(array $item): ?Product
    {
        // Primary: match by Seller SKU
        if (!empty($item['seller_sku'])) {
            $product = Product::where('sku', $item['seller_sku'])->first();
            if ($product) {
                return $product;
            }
        }

        // Fallback: match by Product Name (exact or contains)
        $name = $item['product_name'];
        $variation = $item['variation'] ?? '';

        $product = Product::where('name', $name)->first();
        if (!$product) {
            $product = Product::where('name', 'LIKE', '%' . $name . '%')->first();
        }

        return $product;
    }

    /**
     * Map TikTok order status to ERP status
     */
    private function mapTikTokStatus(string $tiktokStatus): array
    {
        return match (trim($tiktokStatus)) {
            'Selesai' => ['erp_status' => 'COMPLETE', 'description' => 'Order selesai'],
            'Dikirim', 'Sedang transit' => ['erp_status' => 'COMPLETE', 'description' => 'Order dikirim'],
            'Perlu dikirim', 'Menunggu pengiriman' => ['erp_status' => 'OPEN', 'description' => 'Menunggu pengiriman'],
            'Belum dibayar' => ['erp_status' => 'OPEN', 'description' => 'Belum dibayar'],
            'Dibatalkan' => ['erp_status' => 'CANCEL', 'description' => 'Order dibatalkan'],
            default => ['erp_status' => 'OPEN', 'description' => 'Status tidak dikenali: ' . $tiktokStatus],
        };
    }

    /**
     * Get DO status based on ERP status
     */
    private function getDoStatus(string $erpStatus): string
    {
        return match ($erpStatus) {
            'COMPLETE' => 'DELIVERED',
            'CANCEL' => 'CANCEL',
            default => 'DRAFT',
        };
    }

    /**
     * Map TikTok payment method to ERP payment method
     */
    private function mapPaymentMethod(string $tiktokMethod): string
    {
        $method = trim(strtolower($tiktokMethod));

        return match (true) {
            str_contains($method, 'bayar di tempat'), str_contains($method, 'cod') => 'CASH',
            str_contains($method, 'qris') => 'QRIS',
            str_contains($method, 'debit'), str_contains($method, 'paylater') => 'DEBIT',
            str_contains($method, 'dana'), str_contains($method, 'ovo'),
            str_contains($method, 'shopeepay'), str_contains($method, 'gopay'),
            str_contains($method, 'transfer') => 'TRANSFER',
            default => 'TRANSFER',
        };
    }

    /**
     * Get or create generic TikTok Shop Customer
     */
    private function getOrCreateTikTokCustomer(): Customer
    {
        return Customer::firstOrCreate(
            ['code' => 'MKT-TIKTOK'],
            [
                'name' => 'TikTok Shop Customer',
                'address' => '-',
                'phone' => '-',
                'email' => null,
                'pic' => '-',
                'credit_limit' => 0,
                'outstanding_amount' => 0,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get first active warehouse
     */
    private function getFirstActiveWarehouse(): ?Warehouse
    {
        return Warehouse::where('is_active', true)->orderBy('id')->first();
    }

    /**
     * Generate unique number with prefix
     */
    private function generateNumber(string $prefix): string
    {
        $datePrefix = $prefix . '-' . date('Ymd');
        $lastRecord = null;

        // Determine which model/table to check
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
     * Parse date string from TikTok CSV
     */
    private function parseDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr) || trim($dateStr) === '' || trim($dateStr) === ' ') {
            return null;
        }

        $dateStr = trim($dateStr);

        // Try various formats
        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y/m/d',
            'Y-m-d H:i:s',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $dateStr);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }
}