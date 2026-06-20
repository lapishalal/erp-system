<?php

namespace App\Exports;

use App\Models\StockOpname;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class StockOpnameExport implements FromView, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    protected StockOpname $opname;

    public function __construct(StockOpname $opname)
    {
        $this->opname = $opname;
    }

    public function view(): View
    {
        return view('exports.stock-opname', [
            'opname' => $this->opname->load(['details.product', 'warehouse', 'creator', 'approver']),
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['size' => 11]],
            3 => ['font' => ['size' => 11]],
            4 => ['font' => ['size' => 11]],
            6 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E2E8F0']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER,      // Stok Sistem
            'E' => NumberFormat::FORMAT_NUMBER,      // Stok Fisik
            'F' => NumberFormat::FORMAT_NUMBER,      // Selisih
        ];
    }
}