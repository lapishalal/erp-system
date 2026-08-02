<?php

namespace App\Services;

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

/**
 * Service untuk membuat chain POS/SO/DO/Invoice dari MarketplaceOrder yang sudah di-map.
 *
 * Dipanggil dari:
 * - TikTokCsvImportService (saat semua item langsung match)
 * - TikTokUnmappedProductPage (setelah user mapping manual)
 * - TikTokIncomeImportService (saat income datang tapi chain belum ada)
 */
class TikTokOrderProcessingService
{
    /**
     * Create full order chain from a MarketplaceOrder that has all items mapped.
     *
     * @return array Result with keys: action, message, so_number, do_status
     * @throws \Exception If not all items are mapped or chain already exists
     */
    public function createOrderChain(MarketplaceOrder $mkOrder, ?string $forceDoStatus = null): array
    {
        $mkOrder->load('items');

        // Validate: must have items
        if ($mkOrder->items->isEmpty()) {
            throw new \Exception('Tidak ada item pada order ini');
        }

        // Validate: all items must be mapped
        $unmappedCount = $mkOrder->items->where('is_mapped', false)->count();
        if ($unmappedCount > 0) {
            throw new \Exception("Masih ada {$unmappedCount} item yang belum di-map. Silakan map semua produk terlebih dahulu.");
        }

        // Validate: chain not already created
        if ($mkOrder->sales_order_id) {
            return [
                'action' => 'skipped',
                'message' => 'Order chain sudah ada (SO: ' . $mkOrder->salesOrder?->so_number . ')',
                'so_number' => $mkOrder->salesOrder?->so_number,
            ];
        }

        // Validate: order dibatalkan tidak boleh diproses
        if (($mkOrder->status ?? 'OPEN') === 'CANCEL') {
            throw new \Exception('Order berstatus dibatalkan (CANCEL), tidak dapat diproses.');
        }

        return DB::transaction(function () use ($mkOrder, $forceDoStatus) {
            $items = $mkOrder->items;
            $payload = $mkOrder->raw_payload;
            $orderId = $mkOrder->platform_order_id;
            $erpStatus = 'COMPLETE';

            $customer = $this->getOrCreateTikTokCustomer();
            $warehouse = $this->getFirstActiveWarehouse();

            // Build line items (bundle dipecah per komponen)
            $lineItems = [];
            $totalAmount = 0;
            $totalQty = 0;
            $totalCost = 0;

            foreach ($items as $item) {
                $components = $item->mappedComponents();
                if ($components->isEmpty()) {
                    throw new \Exception("Item ID {$item->id} tidak memiliki mapped product");
                }

                $itemSubtotal = (float) ($item->subtotal_after_discount ?? ($item->unit_price * $item->quantity));
                $setQty = max(1, (int) ($item->quantity ?? 1));

                if ($components->count() === 1) {
                    // Produk single
                    $component = $components->first();
                    $product = $component['product'];
                    $qty = $setQty * (int) $component['qty'];
                    $costPrice = $product->getHpp();

                    $lineItems[] = [
                        'product' => $product,
                        'qty' => $qty,
                        'unit_price' => round((float) $item->unit_price, 2),
                        'cost_price' => $costPrice,
                        'subtotal' => round($itemSubtotal, 2),
                    ];
                    $totalAmount += $itemSubtotal;
                    $totalQty += $qty;
                    $totalCost += $costPrice * $qty;
                } else {
                    // Bundle: split subtotal proporsional berdasarkan bobot (default_sale_price x qty)
                    $bobot = [];
                    $totalBobot = 0;
                    foreach ($components as $component) {
                        $w = (float) ($component['product']->default_sale_price ?? 0) * max(1, (int) $component['qty']);
                        $bobot[] = $w;
                        $totalBobot += $w;
                    }

                    if ($totalBobot <= 0) {
                        $totalBobot = $components->count();
                        $bobot = array_fill(0, $components->count(), 1);
                    }

                    foreach ($components as $i => $component) {
                        $product = $component['product'];
                        $compQty = max(1, (int) $component['qty']);
                        $qty = $setQty * $compQty;
                        $share = round($itemSubtotal * ($bobot[$i] / $totalBobot), 2);
                        $unitPrice = $qty > 0 ? round($share / $qty, 2) : 0;
                        $costPrice = $product->getHpp();

                        $lineItems[] = [
                            'product' => $product,
                            'qty' => $qty,
                            'unit_price' => $unitPrice,
                            'cost_price' => $costPrice,
                            'subtotal' => $share,
                        ];
                        $totalAmount += $share;
                        $totalQty += $qty;
                        $totalCost += $costPrice * $qty;
                    }
                }
            }

            $orderDate = $payload['created_time'] ?? now();
            $orderDate = ($orderDate instanceof Carbon) ? $orderDate : Carbon::parse($orderDate);
            $doStatus = $forceDoStatus ?? $this->getDoStatus($erpStatus);

            // 1. Create Sales Order
            $soNumber = $this->generateNumber('SO-TTK');
            $so = SalesOrder::create([
                'so_number' => $soNumber,
                'date' => $orderDate,
                'customer_id' => $customer->id,
                'status' => $erpStatus,
                'source' => 'tiktok',
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $totalAmount - $totalCost,
                'notes' => "TikTok Order: {$orderId}" . ($payload['tracking_id'] ?? '' ? " | Tracking: {$payload['tracking_id']}" : ''),
                'created_by' => auth()->id(),
            ]);

            $soDetails = [];
            foreach ($lineItems as $item) {
                $deliveredQty = in_array($erpStatus, ['COMPLETE']) ? $item['qty'] : 0;
                $remainingQty = $item['qty'] - $deliveredQty;

                $detail = SalesOrderDetail::create([
                    'so_id' => $so->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'delivered_qty' => $deliveredQty,
                    'remaining_qty' => $remainingQty,
                    'subtotal' => $item['subtotal'],
                    'profit' => $item['subtotal'] - ($item['cost_price'] * $item['qty']),
                ]);
                $soDetails[] = $detail;
            }

            // 2. Create Delivery Order (DRAFT first)
            $doNumber = $this->generateNumber('DO-TTK');
            $do = DeliveryOrder::create([
                'do_number' => $doNumber,
                'so_id' => $so->id,
                'date' => $orderDate,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse?->id,
                'status' => 'DRAFT',
                'total_qty' => $totalQty,
                'notes' => "TikTok Order: {$orderId} | " . ($payload['shipping_provider'] ?? '') . " | " . ($payload['tracking_id'] ?? ''),
                'created_by' => auth()->id(),
            ]);

            foreach ($soDetails as $soDetail) {
                DeliveryOrderDetail::create([
                    'do_id' => $do->id,
                    'so_detail_id' => $soDetail->id,
                    'product_id' => $soDetail->product_id,
                    'qty' => $soDetail->qty,
                    'notes' => null,
                ]);
            }

            // 3. Update DO status if shipped/delivered (triggers stock deduction via model event)
            if (in_array($doStatus, ['SHIPPED', 'DELIVERED'])) {
                $do->update(['status' => $doStatus]);
            }

            // 4. Create Sales Invoice
            $invoiceNumber = $this->generateNumber('INV-TTK');
            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'so_id' => $so->id,
                'date' => $orderDate,
                'due_date' => $orderDate->copy()->addDays(30),
                'total' => $totalAmount,
                'paid_amount' => 0,
                'status' => 'UNPAID',
                'notes' => "TikTok Order: {$orderId}",
                'created_by' => auth()->id(),
            ]);

            foreach ($lineItems as $item) {
                SalesInvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // 5. Perbaiki delivered_qty/remaining_qty pada SO details.
            //    Event DeliveryOrderDetail::created menandai semua qty sebagai delivered,
            //    sehingga perlu di-reset sesuai status aktual order.
            $isDelivered = $erpStatus === 'COMPLETE';
            foreach ($so->details()->get() as $detail) {
                $detail->delivered_qty = $isDelivered ? $detail->qty : 0;
                $detail->remaining_qty = $detail->qty - $detail->delivered_qty;
                $detail->saveQuietly();
            }
            $so->refresh();
            $so->updateQuietly(['status' => $erpStatus]);

            // 6. Update marketplace_order: link SO and mark as processed
            $mkOrder->update([
                'sales_order_id' => $so->id,
                'status' => $erpStatus,
                'is_mapped' => true,
                'processed_at' => now(),
            ]);

            Log::info('TikTokOrderProcessing: Chain created', [
                'order_id' => $orderId,
                'so' => $soNumber,
                'do' => $doNumber,
                'inv' => $invoiceNumber,
                'do_status' => $do->status,
            ]);

            return [
                'action' => 'created',
                'message' => "SO={$soNumber}, DO={$doNumber}, INV={$invoiceNumber}",
                'so_number' => $soNumber,
                'do_status' => $doStatus,
                'invoice_id' => $invoice->id,
                'so_id' => $so->id,
            ];
        });
    }

    /**
     * Batalkan seluruh chain order (dipakai untuk retur / re-import status CANCEL).
     *
     * Urutan penting:
     * 1. Invoice -> CANCEL (catat bila sudah PAID untuk peringatan refund)
     * 2. DO -> CANCEL (stok fisik dikembalikan, jurnal DO dihapus via observer)
     * 3. SO -> CANCEL terakhir (mengembalikan outstanding via model event,
     *    dan tidak akan tertimpa oleh observer updateSalesOrderStatus)
     *
     * @return array Result with keys: action, message, was_paid
     */
    public function cancelOrderChain(MarketplaceOrder $mkOrder, bool $isReturn = false): array
    {
        return DB::transaction(function () use ($mkOrder, $isReturn) {
            $so = $mkOrder->salesOrder;

            if (!$so) {
                $mkOrder->update(['status' => 'CANCEL', 'processed_at' => now()]);

                return [
                    'action' => 'cancelled',
                    'message' => 'Order belum diproses, ditandai dibatalkan',
                    'was_paid' => false,
                ];
            }

            $wasPaid = false;

            foreach ($so->salesInvoices as $invoice) {
                if (in_array($invoice->status, ['PAID', 'POSTED'])) {
                    $wasPaid = true;
                }
                if ($invoice->status !== 'CANCEL') {
                    $invoice->update(['status' => 'CANCEL']);
                }
            }

            foreach ($so->deliveryOrders as $do) {
                if ($do->status !== 'CANCEL') {
                    $do->update(['status' => 'CANCEL']);
                }
            }

            if ($so->status !== 'CANCEL') {
                $so->update(['status' => 'CANCEL']);
            }

            $mkOrder->update(['status' => 'CANCEL', 'processed_at' => now()]);

            return [
                'action' => 'cancelled',
                'message' => $wasPaid && $isReturn
                    ? 'Order dibatalkan. PERINGATAN: invoice sudah PAID (settlement sudah masuk). Refund akan terpotong di income berikutnya — harap dicek manual.'
                    : 'Order dibatalkan (stok dikembalikan)',
                'was_paid' => $wasPaid,
            ];
        });
    }

    /**
     * Map a single MarketplaceOrderItem to a Product.
     * Returns true if the entire order is now fully mapped.
     */
    public function mapItem(MarketplaceOrderItem $item, int $productId): bool
    {
        $product = Product::findOrFail($productId);

        $item->update([
            'mapped_product_id' => $productId,
            'is_mapped' => true,
        ]);

        // Check if ALL items in the parent order are now mapped
        $mkOrder = $item->marketplaceOrder;
        $mkOrder->load('items');
        $totalItems = $mkOrder->items->count();
        $mappedItems = $mkOrder->items->where('is_mapped', true)->count();

        if ($mappedItems === $totalItems) {
            $mkOrder->update(['is_mapped' => true, 'error_message' => null]);
            return true;
        }

        return false;
    }

    /**
     * Map a MarketplaceOrderItem to one or more Products (bundle).
     *
     * $components = [['product_id' => int, 'qty' => int], ...]
     * - 1 komponen  -> simpan ke mapped_product_id (backward compatible)
     * - >1 komponen -> simpan ke tabel marketplace_order_item_mappings
     *
     * Returns true if the entire order is now fully mapped.
     */
    public function mapItemWithComponents(MarketplaceOrderItem $item, array $components): bool
    {
        $components = collect($components)
            ->filter(fn ($c) => filled($c['product_id'] ?? null))
            ->map(fn ($c) => [
                'product_id' => (int) $c['product_id'],
                'qty' => max(1, (int) ($c['qty'] ?? 1)),
            ])
            ->values()
            ->all();

        if (empty($components)) {
            throw new \InvalidArgumentException('Pilih minimal satu produk ERP.');
        }

        DB::transaction(function () use ($item, $components) {
            $item->mappings()->delete();

            foreach ($components as $component) {
                Product::findOrFail($component['product_id']);

                $item->mappings()->create([
                    'tenant_id' => $item->tenant_id,
                    'product_id' => $component['product_id'],
                    'qty' => $component['qty'],
                ]);
            }

            $item->update([
                'mapped_product_id' => $components[0]['product_id'],
                'is_mapped' => true,
            ]);
        });

        // Check if ALL items in the parent order are now mapped
        $mkOrder = $item->marketplaceOrder;
        $mkOrder->load('items');
        $totalItems = $mkOrder->items->count();
        $mappedItems = $mkOrder->items->where('is_mapped', true)->count();

        if ($mappedItems === $totalItems) {
            $mkOrder->update(['is_mapped' => true, 'error_message' => null]);
            return true;
        }

        return false;
    }

    /**
     * Map a MarketplaceOrderItem by creating a new product on-the-fly.
     * Returns true if the entire order is now fully mapped.
     */
    public function mapItemWithNewProduct(MarketplaceOrderItem $item, array $productData): bool
    {
        $product = Product::create([
            'code' => $productData['code'],
            'name' => $productData['name'],
            'sku' => $productData['sku'] ?? $item->seller_sku,
            'brand_id' => $productData['brand_id'] ?? null,
            'category_id' => $productData['category_id'] ?? null,
            'unit' => $productData['unit'] ?? 'pcs',
            'default_sale_price' => $productData['default_sale_price'] ?? $item->unit_price ?? 0,
            'last_buy_price' => $productData['last_buy_price'] ?? 0,
            'min_stock' => 0,
            'description' => 'Auto-created from TikTok import: ' . ($item->display_name ?? ''),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $item->update([
            'mapped_product_id' => $product->id,
            'is_mapped' => true,
        ]);

        // Check if all items are now mapped
        $mkOrder = $item->marketplaceOrder;
        $mkOrder->load('items');
        $totalItems = $mkOrder->items->count();
        $mappedItems = $mkOrder->items->where('is_mapped', true)->count();

        if ($mappedItems === $totalItems) {
            $mkOrder->update(['is_mapped' => true, 'error_message' => null]);
            return true;
        }

        return false;
    }

    /**
     * Get or create generic TikTok Shop Customer
     */
    public function getOrCreateTikTokCustomer(): Customer
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
    public function getFirstActiveWarehouse(): ?Warehouse
    {
        return Warehouse::where('is_active', true)->orderBy('id')->first();
    }

    /**
     * Get DO status based on ERP status
     */
    private function getDoStatus(string $erpStatus): string
    {
        return match ($erpStatus) {
            'COMPLETE' => 'SHIPPED',
            'CANCEL' => 'CANCEL',
            default => 'SHIPPED',
        };
    }

    /**
     * Generate unique number with prefix
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
}