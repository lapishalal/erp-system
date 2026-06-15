<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class StockService
{
    public static function addStock(int $productId, int $warehouseId, int $qty, float $price = 0, string $type = 'IN', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $qty, $price, $type, $referenceType, $referenceId, $notes, $userId) {
            $stock = ProductStock::firstOrNew([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ]);

            $stock->physical_stock = ($stock->physical_stock ?? 0) + $qty;
            $stock->available_stock = $stock->physical_stock - ($stock->outstanding_stock ?? 0);
            $stock->save();

            StockTransaction::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty' => $qty,
                'price' => $price,
                'remaining_stock' => $stock->physical_stock,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    public static function deductStock(int $productId, int $warehouseId, int $qty, float $price = 0, string $type = 'OUT', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $qty, $price, $type, $referenceType, $referenceId, $notes, $userId) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (!$stock) {
                throw new \Exception("Stock not found for product ID {$productId} in warehouse {$warehouseId}");
            }

            if ($stock->physical_stock < $qty) {
                throw new \Exception("Insufficient stock for product ID {$productId}. Available: {$stock->physical_stock}, Requested: {$qty}");
            }

            $stock->physical_stock -= $qty;
            $stock->available_stock = $stock->physical_stock - $stock->outstanding_stock;
            $stock->save();

            StockTransaction::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'qty' => -$qty,
                'price' => $price,
                'remaining_stock' => $stock->physical_stock,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    public static function addOutstanding(int $productId, int $warehouseId, int $qty): void
    {
        $stock = ProductStock::firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);

        $stock->outstanding_stock = ($stock->outstanding_stock ?? 0) + $qty;
        $stock->available_stock = ($stock->physical_stock ?? 0) - $stock->outstanding_stock;
        $stock->save();
    }

    public static function deductOutstanding(int $productId, int $warehouseId, int $qty): void
    {
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock) {
            $stock->outstanding_stock = max(0, $stock->outstanding_stock - $qty);
            $stock->available_stock = $stock->physical_stock - $stock->outstanding_stock;
            $stock->save();
        }
    }

    public static function adjustStock(int $productId, int $warehouseId, int $newQty, ?string $notes = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $newQty, $notes, $userId) {
            $stock = ProductStock::firstOrNew([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ]);

            $difference = $newQty - ($stock->physical_stock ?? 0);

            $stock->physical_stock = $newQty;
            $stock->available_stock = $newQty - ($stock->outstanding_stock ?? 0);
            $stock->save();

            if ($difference !== 0) {
                StockTransaction::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'ADJUSTMENT',
                    'qty' => $difference,
                    'price' => 0,
                    'remaining_stock' => $newQty,
                    'notes' => $notes ?? 'Stock adjustment',
                    'created_by' => $userId,
                ]);
            }
        });
    }
}