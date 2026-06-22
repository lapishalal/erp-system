<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\ProductStock;
use App\Models\SalesOrderDetail;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Inject display fields (so_number, product_name, remaining_qty, etc)
        $details = [];
        foreach ($data['details'] ?? [] as $item) {
            $soDetail = SalesOrderDetail::with(['product', 'salesOrder'])->find($item['so_detail_id'] ?? null);
            $stock = ProductStock::where('product_id', $item['product_id'] ?? null)
                ->where('warehouse_id', $data['warehouse_id'] ?? null)
                ->first();

            $remaining = $soDetail?->remaining_qty ?? 0;
            $stockQty = $stock?->available_stock ?? 0;
            $qty = $item['qty'] ?? 0;

            $details[] = array_merge($item, [
                'so_number' => $soDetail?->salesOrder?->so_number ?? '-',
                'product_name' => $soDetail?->product?->name ?? '-',
                'remaining_qty' => $remaining,
                'available_stock' => $stockQty,
                'remaining_after' => max(0, $remaining - $qty),
                'stock_after' => max(0, $stockQty - $qty),
            ]);
        }

        $data['details'] = $details;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
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

        $record->update($data);

        return $record;
    }

    // =========================================================
    // FIX: Recalculate total_qty setelah save (fallback)
    // =========================================================
    protected function afterSave(): void
    {
        $totalQty = $this->record->details()->sum('qty');

        // Gunakan DB::table untuk avoid triggering events lagi
        DB::table('delivery_orders')
            ->where('id', $this->record->id)
            ->update(['total_qty' => $totalQty]);
    }
}