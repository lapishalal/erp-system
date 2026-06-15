<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $products = Product::with(['stock', 'brand', 'category'])
            ->where('is_active', true)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'default_sale_price' => $product->default_sale_price,
                    'last_buy_price' => $product->last_buy_price,
                    'min_stock' => $product->min_stock,
                    'brand' => $product->brand ? ['name' => $product->brand->name] : null,
                    'category' => $product->category ? ['name' => $product->category->name] : null,
                    'stock' => $product->stock ? [
                        'physical_stock' => $product->stock->physical_stock,
                        'available_stock' => $product->stock->available_stock,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}