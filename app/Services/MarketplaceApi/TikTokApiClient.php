<?php

namespace App\Services\MarketplaceApi;

use Illuminate\Support\Facades\Log;

class TikTokApiClient
{
    public function __construct(
        private string $accessToken,
        private string $shopId
    ) {}

    public function updateStock(string $sku, int $availableStock): bool
    {
        // TODO: Ganti dengan API call TikTok Shop yang sebenarnya
        // Dokumentasi: https://partner.tiktokshop.com/docv2/page/650301d902a7350294e4c9b8

        Log::info('[TIKTOK API MOCK] Update stock', [
            'shop_id' => $this->shopId,
            'sku' => $sku,
            'stock' => $availableStock,
        ]);

        // Simulasi sukses
        return true;
    }
}