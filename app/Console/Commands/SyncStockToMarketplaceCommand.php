<?php

namespace App\Console\Commands;

use App\Jobs\SyncStockToMarketplace;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncStockToMarketplaceCommand extends Command
{
    protected $signature = 'sync:stock-marketplace {tenant} {product?}';
    protected $description = 'Sync stok produk ke marketplace';

    public function handle(): void
    {
        $tenantId = $this->argument('tenant');
        $productId = $this->argument('product');

        if ($productId) {
            $this->info("Syncing product ID {$productId}...");
            SyncStockToMarketplace::dispatch((int) $productId, $tenantId);
        } else {
            $products = Product::where('tenant_id', $tenantId)
                ->whereNotNull('sku')
                ->get();

            $this->info("Syncing {$products->count()} products...");

            foreach ($products as $product) {
                SyncStockToMarketplace::dispatch($product->id, $tenantId);
            }
        }

        $this->info('Done! Check logs for details.');
    }
}