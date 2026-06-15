<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'kode',
            'nama',
            'brand',
            'kategori',
            'satuan',
            'harga_jual',
            'min_stok',
            'stok_awal',
            'deskripsi',
        ];
    }

    public function array(): array
    {
        return [
            ['SKU-001', 'Laptop ASUS VivoBook', 'ASUS', 'Elektronik', 'pcs', 8500000, 5, 10, 'Laptop 14 inch'],
            ['SKU-002', 'Mouse Logitech', 'Logitech', 'Aksesoris', 'pcs', 250000, 20, 50, 'Mouse wireless'],
        ];
    }
}