<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Services\TikTokCsvImportService;
use App\Services\TikTokIncomeImportService;
use App\Services\TikTokOrderProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TikTokImportController extends Controller
{
    /**
     * Import TikTok Order CSV
     */
    public function importOrders(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $filePath = $file->storeAs('imports/tiktok', 'orders_' . now()->format('Ymd_His') . '.csv', 'public');
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            $service = new TikTokCsvImportService();
            $results = $service->importOrders($fullPath);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import selesai: %d diproses, %d baru, %d diupdate, %d dilewati, %d error',
                    $results['total'],
                    $results['created'],
                    $results['updated'],
                    $results['skipped'],
                    $results['errors']
                ),
                'data' => $results,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import TikTok Income CSV
     */
    public function importIncome(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $filePath = $file->storeAs('imports/tiktok', 'income_' . now()->format('Ymd_His') . '.csv', 'public');
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            $service = new TikTokIncomeImportService();
            $results = $service->importIncome($fullPath);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import income selesai: %d diproses, %d baru/dibayar, %d diupdate, %d dilewati, %d error',
                    $results['total'],
                    $results['created'],
                    $results['updated'],
                    $results['skipped'],
                    $results['errors']
                ),
                'data' => $results,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import income: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map a single unmapped MarketplaceOrderItem to an existing Product.
     * Returns JSON with order mapping status.
     */
    public function mapItem(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:marketplace_order_items,id',
            'product_id' => 'required|exists:products,id',
        ]);

        try {
            $item = MarketplaceOrderItem::findOrFail($request->input('item_id'));
            $processingService = new TikTokOrderProcessingService();
            $isFullyMapped = $processingService->mapItem($item, (int) $request->input('product_id'));

            return response()->json([
                'success' => true,
                'is_fully_mapped' => $isFullyMapped,
                'order_id' => $item->refresh()->marketplaceOrder->platform_order_id,
                'message' => $isFullyMapped
                    ? 'Item di-map. Semua item order sudah lengkap, siap diproses.'
                    : 'Item berhasil di-map. Masih ada item lain yang belum di-map.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map a single unmapped MarketplaceOrderItem by creating a new Product on-the-fly.
     * Returns JSON with order mapping status.
     */
    public function mapItemWithNewProduct(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:marketplace_order_items,id',
            'product_code' => 'required|string|max:50',
            'product_name' => 'required|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'default_sale_price' => 'nullable|numeric|min:0',
            'last_buy_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:20',
        ]);

        try {
            $item = MarketplaceOrderItem::findOrFail($request->input('item_id'));
            $processingService = new TikTokOrderProcessingService();
            $isFullyMapped = $processingService->mapItemWithNewProduct($item, [
                'code' => $request->input('product_code'),
                'name' => $request->input('product_name'),
                'sku' => $request->input('product_sku', $item->seller_sku),
                'default_sale_price' => $request->input('default_sale_price', $item->unit_price),
                'last_buy_price' => $request->input('last_buy_price', 0),
                'unit' => $request->input('unit', 'pcs'),
            ]);

            return response()->json([
                'success' => true,
                'is_fully_mapped' => $isFullyMapped,
                'order_id' => $item->refresh()->marketplaceOrder->platform_order_id,
                'message' => $isFullyMapped
                    ? 'Produk baru dibuat & item di-map. Semua item order sudah lengkap, siap diproses.'
                    : 'Produk baru dibuat & item di-map. Masih ada item lain yang belum di-map.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-match all unmapped items by Seller SKU.
     * Returns counts of matched and failed.
     */
    public function autoMatchAll(Request $request): JsonResponse
    {
        try {
            $unmappedItems = MarketplaceOrderItem::unmapped()->get();
            $matched = 0;
            $failed = 0;

            $processingService = new TikTokOrderProcessingService();

            foreach ($unmappedItems as $item) {
                if (empty($item->seller_sku)) {
                    $failed++;
                    continue;
                }

                $product = \App\Models\Product::where('sku', $item->seller_sku)->first();
                if (!$product) {
                    $failed++;
                    continue;
                }

                try {
                    $processingService->mapItem($item, $product->id);
                    $matched++;
                } catch (\Throwable $e) {
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'matched' => $matched,
                'failed' => $failed,
                'message' => "Auto-match selesai: {$matched} berhasil, {$failed} gagal",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process all fully-mapped orders that don't have a chain yet.
     * Creates POS/SO/DO/Invoice for each.
     */
    public function processMappedOrders(Request $request): JsonResponse
    {
        try {
            $orders = MarketplaceOrder::query()
                ->where('is_mapped', true)
                ->whereNull('sales_order_id')
                ->where('platform', 'tiktok')
                ->get();

            $success = 0;
            $errors = 0;
            $errorDetails = [];

            $processingService = new TikTokOrderProcessingService();

            foreach ($orders as $mkOrder) {
                try {
                    $result = $processingService->createOrderChain($mkOrder);
                    if (($result['action'] ?? '') === 'created') {
                        $success++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $errorDetails[] = [
                        'order_id' => $mkOrder->platform_order_id,
                        'error' => $e->getMessage(),
                    ];
                    \Illuminate\Support\Facades\Log::error('Process mapped order failed', [
                        'order_id' => $mkOrder->platform_order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => $errors === 0,
                'processed' => $success,
                'errors' => $errors,
                'error_details' => $errorDetails,
                'message' => $errors === 0
                    ? "{$success} order berhasil diproses!"
                    : "{$success} berhasil, {$errors} gagal. Cek detail error.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unmapped items list with order info (for API/JS usage).
     */
    public function getUnmappedItems(Request $request): JsonResponse
    {
        $query = MarketplaceOrderItem::query()
            ->unmapped()
            ->with(['marketplaceOrder'])
            ->orderByDesc('created_at');

        // Optional search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'LIKE', "%{$search}%")
                    ->orWhere('seller_sku', 'LIKE', "%{$search}%")
                    ->orWhere('variation', 'LIKE', "%{$search}%")
                    ->orWhereHas('marketplaceOrder', fn($mq) => $mq->where('platform_order_id', 'LIKE', "%{$search}%"));
            });
        }

        $items = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}