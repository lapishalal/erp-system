<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesReportExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    protected ?string $dari;
    protected ?string $sampai;
    protected ?int $customerId;
    protected ?string $status;
    protected ?int $brandId;
    protected ?int $productId;

    public function __construct(array $filters = [])
    {
        $this->dari = $filters['dari'] ?? now()->startOfMonth()->toDateString();
        $this->sampai = $filters['sampai'] ?? now()->endOfMonth()->toDateString();
        $this->customerId = $filters['customer_id'] ?? null;
        $this->status = $filters['status'] ?? null;
        $this->brandId = $filters['brand_id'] ?? null;
        $this->productId = $filters['product_id'] ?? null;
    }

    public function query(): Builder
    {
        return SalesOrder::query()
            ->with(['customer', 'details.product.brand'])
            ->when($this->dari, fn ($q) => $q->whereDate('date', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('date', '<=', $this->sampai))
            ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->brandId, function ($q) {
                $q->whereHas('details.product', fn ($q) => $q->where('brand_id', $this->brandId));
            })
            ->when($this->productId, function ($q) {
                $q->whereHas('details', fn ($q) => $q->where('product_id', $this->productId));
            })
            ->orderBy('date', 'desc');
    }

    public function headings(): array
    {
        return [
            'No. SO',
            'Tanggal',
            'Customer',
            'Brand',
            'Total Qty',
            'Total Omset',
            'Total HPP',
            'Profit',
            'Margin %',
            'Status',
        ];
    }

    public function map($row): array
    {
        $margin = $row->total_amount > 0 ? ($row->profit / $row->total_amount) * 100 : 0;

        return [
            $row->so_number,
            $row->date->format('d/m/Y'),
            $row->customer?->name ?? '-',
            $row->details->first()?->product?->brand?->name ?? '-',
            $row->total_qty,
            $row->total_amount,
            $row->total_cost,
            $row->profit,
            number_format($margin, 1) . '%',
            $row->status,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}