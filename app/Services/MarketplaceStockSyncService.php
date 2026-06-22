<?php

namespace App\Services;

use App\Enums\MarketplacePlatform;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Services\MarketplaceApi\ShopeeApiClient;
use App\Services\MarketplaceApi\TikTokApiClient;
use Illuminate\Support\Facades\Log;

class MarketplaceStockSyncService
{
    public function syncProduct(int $productId, string $tenantId): void
    {
        $product = Product::where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            Log::warning('StockSync: Product not found', [
                'product_id' => $productId,
                'tenant_id' => $tenantId,
            ]);
            return;
        }

        $availableStock = $this->getAvailableStock($product);

        // Sync ke semua marketplace connection aktif
        $connections = MarketplaceConnection::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            $this->pushToMarketplace($connection, $product, $availableStock);
        }
    }

    /**
     * TODO: Sesuaikan dengan cara Anda menghitung stok
     */
    private function getAvailableStock(Product $product): int
    {
        // Ambil dari tabel product_stocks (sum semua warehouse)
        $totalAvailable = \App\Models\ProductStock::where('product_id', $product->id)
            ->where('tenant_id', $product->tenant_id)
            ->sum('available_stock');

        return (int) $totalAvailable;
    }

    private function pushToMarketplace(MarketplaceConnection $connection, Product $product, int $stock): void
    {
        $platform = $connection->platform;

        try {
            if ($platform === MarketplacePlatform::TIKTOK) {
                $client = new TikTokApiClient(
                    $connection->access_token ?? 'dummy-token',
                    $connection->shop_id ?? '0'
                );
                $client->updateStock($product->sku, $stock);
            }

            if ($platform === MarketplacePlatform::SHOPEE) {
                $client = new ShopeeApiClient(
                    $connection->access_token ?? 'dummy-token',
                    (int) ($connection->shop_id ?? 0)
                );
                // Untuk Shopee perlu item_id mapping, sementara pakai product_id
                $client->updateStock($product->id, $stock);
            }

            Log::info('StockSync: Push success', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'platform' => $platform->value,
                'stock' => $stock,
            ]);

        } catch (\Throwable $e) {
            Log::error('StockSync: Push failed', [
                'product_id' => $product->id,
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}