<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'details.product']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->orderBy('date', 'desc')->paginate(20));
    }

    public function show($id)
    {
        $so = SalesOrder::with(['customer', 'details.product.brand', 'deliveryOrders.details'])->findOrFail($id);
        return response()->json($so);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'notes' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $soNumber = 'SO-' . date('Ymd') . '-' . rand(1000, 9999);

            $totalQty = 0;
            $totalAmount = 0;
            $totalCost = 0;

            $so = SalesOrder::create([
                'so_number' => $soNumber,
                'date' => $validated['date'],
                'customer_id' => $validated['customer_id'],
                'status' => 'OPEN',
                'notes' => $validated['notes'] ?? null,
                'total_qty' => 0,
                'total_amount' => 0,
                'total_cost' => 0,
                'profit' => 0,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $costPrice = $product->last_buy_price ?? 0;
                $subtotal = $item['qty'] * $item['unit_price'];
                $costTotal = $item['qty'] * $costPrice;

                SalesOrderDetail::create([
                    'so_id' => $so->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $costPrice,
                    'delivered_qty' => 0,
                    'remaining_qty' => $item['qty'],
                    'subtotal' => $subtotal,
                    'profit' => $subtotal - $costTotal,
                ]);

                $totalQty += $item['qty'];
                $totalAmount += $subtotal;
                $totalCost += $costTotal;

                // Add outstanding
                StockService::addOutstanding($item['product_id'], 1, $item['qty']);
            }

            $so->update([
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $totalAmount - $totalCost,
            ]);

            DB::commit();

            return response()->json($so->load('details'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deliveryOrders(Request $request)
    {
        $query = DeliveryOrder::with(['customer', 'salesOrder', 'details.product']);

        if ($request->has('so_id')) {
            $query->where('so_id', $request->so_id);
        }

        return response()->json($query->orderBy('date', 'desc')->paginate(20));
    }
}