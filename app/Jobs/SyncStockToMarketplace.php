<?php

namespace App\Jobs;

use App\Services\MarketplaceStockSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStockToMarketplace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $productId,
        public string $tenantId
    ) {}

    public function handle(MarketplaceStockSyncService $service): void
    {
        $service->syncProduct($this->productId, $this->tenantId);
    }
}