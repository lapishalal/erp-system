<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\ProductStock;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $product = $this->record;

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => 1,
            'physical_stock' => 0,
            'outstanding_stock' => 0,
            'available_stock' => 0,
        ]);
    }
}