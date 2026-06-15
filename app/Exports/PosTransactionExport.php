<?php

namespace App\Exports;

use App\Models\PosTransaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PosTransactionExport implements FromQuery, WithHeadings, WithMapping
{
    protected ?string $dari;
    protected ?string $sampai;
    protected ?string $paymentMethod;
    protected ?int $createdBy;

    public function __construct(array $filters = [])
    {
        $this->dari = $filters['dari'] ?? null;
        $this->sampai = $filters['sampai'] ?? null;
        $this->paymentMethod = $filters['payment_method'] ?? null;
        $this->createdBy = $filters['created_by'] ?? null;
    }

    public function query(): Builder
    {
        return PosTransaction::query()
            ->with(['details.product', 'customer', 'creator'])
            ->when($this->dari, fn ($q) => $q->whereDate('date', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('date', '<=', $this->sampai))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->createdBy, fn ($q) => $q->where('created_by', $this->createdBy))
            ->orderBy('date', 'desc');
    }

    public function headings(): array
    {
        return [
            'No. Transaksi', 'Tanggal', 'Kasir', 'Customer', 'Metode Bayar',
            'Subtotal', 'Diskon', 'Total', 'Bayar', 'Kembalian', 'Qty Item', 'Barang'
        ];
    }

    public function map($row): array
    {
        return [
            $row->transaction_number,
            $row->date->format('d/m/Y H:i'),
            $row->creator?->name ?? '-',
            $row->customer?->name ?? 'Walk-in',
            match($row->payment_method) {
                'CASH' => 'Tunai',
                'DEBIT' => 'Debit',
                'QRIS' => 'QRIS',
                'TRANSFER' => 'Transfer',
                default => $row->payment_method,
            },
            $row->subtotal,
            $row->discount,
            $row->total,
            $row->paid_amount,
            $row->change_amount,
            $row->total_qty,
            $row->details->map(fn ($d) => $d->product->name . ' x' . $d->qty)->join(', '),
        ];
    }
}