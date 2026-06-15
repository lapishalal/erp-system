<?php

namespace App\Exports;

use App\Models\StockTransaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockReportExport implements FromQuery, WithHeadings, WithMapping
{
    protected ?string $dari;
    protected ?string $sampai;
    protected ?int $warehouseId;
    protected ?int $productId;
    protected ?string $type;

    public function __construct(array $filters = [])
    {
        $this->dari = $filters['dari'] ?? null;
        $this->sampai = $filters['sampai'] ?? null;
        $this->warehouseId = $filters['warehouse_id'] ?? null;
        $this->productId = $filters['product_id'] ?? null;
        $this->type = $filters['type'] ?? null;
    }

    public function query(): Builder
    {
        return StockTransaction::query()
            ->with(['product', 'warehouse'])
            ->when($this->dari, fn ($q) => $q->whereDate('created_at', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('created_at', '<=', $this->sampai))
            ->when($this->warehouseId, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->when($this->productId, fn ($q) => $q->where('product_id', $this->productId))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return ['Tanggal', 'Kode', 'Barang', 'Gudang', 'Jenis', 'Qty', 'Stok Akhir', 'Referensi', 'Keterangan'];
    }

    public function map($row): array
    {
        return [
            $row->created_at->format('d/m/Y H:i'),
            $row->product?->code ?? '-',
            $row->product?->name ?? '-',
            $row->warehouse?->name ?? '-',
            match($row->type) {
                'IN' => 'Masuk',
                'OUT' => 'Keluar',
                'ADJUSTMENT' => 'Penyesuaian',
                'OPNAME' => 'Opname',
                default => $row->type,
            },
            $row->qty,
            $row->remaining_stock,
            $row->reference_type ? class_basename($row->reference_type) . ' #' . $row->reference_id : '-',
            $row->notes ?? '-',
        ];
    }
}