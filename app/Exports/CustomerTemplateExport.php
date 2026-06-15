<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'kode',
            'nama',
            'alamat',
            'telepon',
            'email',
            'pic',
            'limit_kredit',
        ];
    }

    public function array(): array
    {
        return [
            ['CUST-001', 'PT Maju Jaya', 'Jl. Sudirman No. 1, Jakarta', '08123456789', 'maju@email.com', 'Budi', 50000000],
            ['CUST-002', 'CV Sejahtera', 'Jl. Thamrin No. 5, Bandung', '08234567890', 'sejahtera@email.com', 'Ani', 25000000],
        ];
    }
}