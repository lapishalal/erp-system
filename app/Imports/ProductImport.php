<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): ?Product
    {
        if (!empty($row['kode']) && Product::where('code', $row['kode'])->exists()) {
            return null;
        }

        $brand = null;
        if (!empty($row['brand'])) {
            $brand = Brand::firstOrCreate(
                ['name' => trim($row['brand'])],
                ['code' => 'BR-' . strtoupper(uniqid()), 'is_active' => true]
            );
        }

        $category = null;
        if (!empty($row['kategori'])) {
            $category = Category::firstOrCreate(
                ['name' => trim($row['kategori'])],
                ['is_active' => true]
            );
        }

        $product = Product::create([
            'code'              => $row['kode'] ?? 'SKU-' . strtoupper(uniqid()),
            'name'              => $row['nama'],
            'brand_id'          => $brand?->id,
            'category_id'       => $category?->id,
            'unit'              => $row['satuan'] ?? 'pcs',
            'default_sale_price'=> $row['harga_jual'] ?? 0,
            'min_stock'         => $row['min_stok'] ?? 0,
            'description'       => $row['deskripsi'] ?? null,
            'is_active'         => true,
        ]);

        ProductStock::create([
            'product_id'        => $product->id,
            'warehouse_id'      => 1,
            'physical_stock'    => $row['stok_awal'] ?? 0,
            'outstanding_stock' => 0,
            'available_stock'   => $row['stok_awal'] ?? 0,
        ]);

        return $product;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'satuan' => 'nullable|string|max:50',
            'harga_jual' => 'nullable|numeric',
            'min_stok' => 'nullable|integer',
            'stok_awal' => 'nullable|integer|min:0',
            'deskripsi' => 'nullable|string',
        ];
    }
}