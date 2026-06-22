<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\ProductStock;
use App\Models\SalesOrderDetail;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Filter hanya item dengan qty > 0
        $filtered = [];
        foreach ($data['details'] ?? [] as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            // Validasi max
            $soDetail = SalesOrderDetail::find($item['so_detail_id'] ?? null);
            $stock = ProductStock::where('product_id', $item['product_id'] ?? null)
                ->where('warehouse_id', $data['warehouse_id'] ?? null)
                ->first();

            $remaining = $soDetail ? $soDetail->remaining_qty : 0;
            $available = $stock ? $stock->available_stock : 0;
            $max = min($remaining, $available);

            if ($qty > $max) {
                $qty = $max;
            }

            if ($qty > 0) {
                $filtered[] = [
                    'so_detail_id' => $item['so_detail_id'],
                    'product_id' => $item['product_id'],
                    'qty' => $qty,
                    'notes' => $item['notes'] ?? null,
                ];
            }
        }

        $data['details'] = $filtered;
        $data['total_qty'] = array_sum(array_column($filtered, 'qty'));

        return $data;
    }
}