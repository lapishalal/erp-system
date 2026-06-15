<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\StockTransaction;
use App\Services\StockService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function stock(Request $request)
    {
        $query = ProductStock::with(['product.brand', 'warehouse']);

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('low_stock')) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('product_stocks.physical_stock', '<=', 'products.min_stock')
                  ->where('products.min_stock', '>', 0);
            });
        }

        return response()->json($query->paginate(50));
    }

    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|integer|min:1',
            'price' => 'nullable|numeric',
            'notes' => 'nullable',
        ]);

        StockService::addStock(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['qty'],
            $validated['price'] ?? 0,
            'IN',
            null,
            null,
            $validated['notes'] ?? 'Stock masuk via API',
            auth()->id()
        );

        return response()->json(['message' => 'Stock added']);
    }

    public function stockOut(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable',
        ]);

        StockService::deductStock(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['qty'],
            0,
            'OUT',
            null,
            null,
            $validated['notes'] ?? 'Stock keluar via API',
            auth()->id()
        );

        return response()->json(['message' => 'Stock deducted']);
    }

    public function stockOpname(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'opname_date' => 'required|date',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.physical_qty' => 'required|integer|min:0',
        ]);

        $opname = StockOpname::create([
            'warehouse_id' => $validated['warehouse_id'],
            'opname_date' => $validated['opname_date'],
            'status' => 'DRAFT',
            'created_by' => auth()->id(),
        ]);

        foreach ($validated['items'] as $item) {
            $stock = ProductStock::where('product_id', $item['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->first();

            $systemQty = $stock?->physical_stock ?? 0;

            StockOpnameDetail::create([
                'opname_id' => $opname->id,
                'product_id' => $item['product_id'],
                'system_qty' => $systemQty,
                'physical_qty' => $item['physical_qty'],
                'difference_qty' => $item['physical_qty'] - $systemQty,
            ]);
        }

        return response()->json($opname->load('details'), 201);
    }
}