<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CustomerReportExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    protected ?string $dari;
    protected ?string $sampai;
    protected ?string $status;

    public function __construct(?string $dari = null, ?string $sampai = null, ?string $status = null)
    {
        $this->dari = $dari;
        $this->sampai = $sampai;
        $this->status = $status;
    }

    public function query(): Builder
    {
        $tenantId = auth()->user()->tenant_id ?? null;

        $dari = $this->dari;
        $sampai = $this->sampai;
        $status = $this->status;

        $subquery = DB::table('sales_orders')
            ->select('customer_id')
            ->selectRaw('SUM(total_amount) as total_omset')
            ->selectRaw('SUM(total_cost) as total_hpp')
            ->selectRaw('SUM(profit) as total_profit')
            ->selectRaw('COUNT(*) as total_orders')
            ->where('status', '!=', 'CANCEL')
            ->where('tenant_id', $tenantId)
            ->when($dari, fn ($q) => $q->whereDate('date', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('date', '<=', $sampai))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->groupBy('customer_id');

        return Customer::query()
            ->select('customers.*')
            ->selectRaw('COALESCE(agg.total_omset, 0) as total_omset')
            ->selectRaw('COALESCE(agg.total_hpp, 0) as total_hpp')
            ->selectRaw('COALESCE(agg.total_profit, 0) as total_profit')
            ->selectRaw('COALESCE(agg.total_orders, 0) as total_orders')
            ->leftJoinSub($subquery, 'agg', 'customers.id', '=', 'agg.customer_id')
            ->where('customers.tenant_id', $tenantId)
            ->orderByDesc('total_omset');
    }

    public function headings(): array
    {
        return [
            'Kode Customer',
            'Nama Customer',
            'Jumlah Order',
            'Total Omset',
            'Total HPP',
            'Profit',
            'Margin %',
        ];
    }

    public function map($row): array
    {
        $omset = (float) $row->total_omset;
        $profit = (float) $row->total_profit;
        $margin = $omset > 0 ? ($profit / $omset) * 100 : 0;

        return [
            $row->code,
            $row->name,
            $row->total_orders,
            $row->total_omset,
            $row->total_hpp,
            $row->total_profit,
            number_format($margin, 1) . '%',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
