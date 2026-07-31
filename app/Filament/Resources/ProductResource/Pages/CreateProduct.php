<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\ProductStock;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['default_sale_price'] = $this->normalizePrice($data['default_sale_price'] ?? null);
        $data['last_buy_price'] = $this->normalizePrice($data['last_buy_price'] ?? null);

        return $data;
    }

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

    private function normalizePrice(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) preg_replace('/[^0-9.-]/', '', (string) $value);
    }
}