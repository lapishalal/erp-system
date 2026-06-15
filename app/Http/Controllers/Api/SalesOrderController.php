<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::with(['customer', 'details.product'])
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'so_number' => $order->so_number,
                    'date' => $order->date,
                    'customer' => $order->customer ? ['name' => $order->customer->name] : null,
                    'status' => $order->status,
                    'total_qty' => $order->total_qty,
                    'total_amount' => $order->total_amount,
                    'total_cost' => $order->total_cost,
                    'profit' => $order->profit,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.qty' => 'required|integer|min:1',
            'details.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $totalQty = 0;
            $totalAmount = 0;
            $totalCost = 0;

            foreach ($validated['details'] as $item) {
                $product = Product::find($item['product_id']);
                $totalQty += $item['qty'];
                $totalAmount += $item['qty'] * $item['unit_price'];
                $totalCost += $item['qty'] * ($product->last_buy_price ?? 0);
            }

            $so = SalesOrder::create([
                'so_number' => 'SO-' . date('Ymd') . '-' . rand(1000, 9999),
                'customer_id' => $validated['customer_id'],
                'date' => $validated['date'],
                'status' => 'OPEN',
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $totalAmount - $totalCost,
                'notes' => 'Created from mobile app',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['details'] as $item) {
                $product = Product::find($item['product_id']);
                SalesOrderDetail::create([
                    'so_id' => $so->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $product->last_buy_price ?? 0,
                    'subtotal' => $item['qty'] * $item['unit_price'],
                    'profit' => ($item['unit_price'] - ($product->last_buy_price ?? 0)) * $item['qty'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $so,
                'message' => 'Sales Order berhasil dibuat',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}