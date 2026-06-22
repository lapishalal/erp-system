<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductSkuFromCode extends Command
{
    protected $signature = 'sync:product-sku {tenant?}';
    protected $description = 'Copy product code ke sku untuk data existing';

    public function handle(): void
    {
        $query = DB::table('products')->whereNull('sku');

        if ($this->argument('tenant')) {
            $query->where('tenant_id', $this->argument('tenant'));
        }

        $updated = $query->update(['sku' => DB::raw('code')]);

        $this->info("{$updated} produk di-update: code → sku");
    }
}