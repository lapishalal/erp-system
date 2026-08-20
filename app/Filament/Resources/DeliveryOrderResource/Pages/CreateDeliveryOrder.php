<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\ProductStock;
use App\Models\SalesOrderDetail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $isDropship = (bool) ($data['is_dropship'] ?? false);

        $errors = [];
        $filtered = [];
        foreach ($data['details'] ?? [] as $index => $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $soDetail = SalesOrderDetail::find($item['so_detail_id'] ?? null);
            $remaining = $soDetail ? $soDetail->remaining_qty : 0;

            if ($isDropship) {
                $max = $remaining;
            } else {
                $stock = ProductStock::where('product_id', $item['product_id'] ?? null)
                    ->where('warehouse_id', $data['warehouse_id'] ?? null)
                    ->first();
                $available = $stock ? $stock->available_stock : 0;
                $max = min($remaining, $available);
            }

            if ($qty > $max) {
                $errors['details.' . $index . '.qty'] = 'Jumlah kirim (' . $qty . ') melebihi maksimal ' . $max . ' unit (' .
                    ($soDetail?->product?->name ?? 'barang') . ').';
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

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $data['details'] = $filtered;
        $data['total_qty'] = array_sum(array_column($filtered, 'qty'));

        return $data;
    }
}