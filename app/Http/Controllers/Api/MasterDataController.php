<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function products(Request $request)
    {
        $query = Product::with(['brand', 'category', 'stock']);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        return response()->json($query->paginate(50));
    }

    public function productDetail($id)
    {
        $product = Product::with(['brand', 'category', 'stock', 'buyPrices.supplier'])->findOrFail($id);

        // Outstanding per customer
        $outstanding = \App\Models\SalesOrderDetail::with(['salesOrder.customer'])
            ->where('product_id', $id)
            ->where('remaining_qty', '>', 0)
            ->get()
            ->map(function ($d) {
                return [
                    'customer' => $d->salesOrder->customer->name ?? '-',
                    'so_number' => $d->salesOrder->so_number,
                    'remaining' => $d->remaining_qty,
                ];
            });

        return response()->json([
            'product' => $product,
            'outstanding_customers' => $outstanding,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:products',
            'name' => 'required',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'unit' => 'required',
            'default_sale_price' => 'required|numeric',
            'min_stock' => 'integer',
        ]);

        $product = Product::create($validated + ['created_by' => auth()->id()]);

        // Create initial stock
        \App\Models\ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => 1,
            'physical_stock' => 0,
            'outstanding_stock' => 0,
            'available_stock' => 0,
        ]);

        return response()->json($product, 201);
    }

    public function customers(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->paginate(50));
    }

    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:customers',
            'name' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable',
            'email' => 'nullable|email',
            'credit_limit' => 'nullable|numeric',
        ]);

        $customer = Customer::create($validated);
        return response()->json($customer, 201);
    }

    public function brands()
    {
        return response()->json(Brand::where('is_active', true)->get());
    }

    public function categories()
    {
        return response()->json(Category::where('is_active', true)->get());
    }

    public function suppliers()
    {
        return response()->json(Supplier::where('is_active', true)->get());
    }
}