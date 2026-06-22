<?php

namespace App\Services\MarketplaceApi;

use Illuminate\Support\Facades\Log;

class ShopeeApiClient
{
    public function __construct(
        private string $accessToken,
        private int $shopId
    ) {}

    public function updateStock(int $itemId, int $stock): bool
    {
        // TODO: Ganti dengan API call Shopee OpenAPI yang sebenarnya
        // Dokumentasi: https://open.shopee.com/documents/v2/v2.product.update_stock

        Log::info('[SHOPEE API MOCK] Update stock', [
            'shop_id' => $this->shopId,
            'item_id' => $itemId,
            'stock' => $stock,
        ]);

        // Simulasi sukses
        return true;
    }
}