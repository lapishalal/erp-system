<?php

namespace App\Filament\Components;

use App\Models\SalesInvoice;
use Filament\Infolists\Components\Entry;

class PaymentHistory extends Entry
{
    protected string $view = 'filament.components.payment-history';

    public function getState(): array
    {
        $record = $this->getRecord(); // SalesInvoice model

        if (!$record instanceof SalesInvoice) {
            return [];
        }

        $payments = $record->cashIns()
            ->where('type', 'CUSTOMER_PAYMENT')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $total = (float) $record->total;
        $paid = (float) $payments->sum('amount');
        $adminFee = (float) ($record->salesOrder?->admin_fee ?? 0);

        return [
            'total' => $total,
            'paid' => $paid,
            'admin_fee' => $adminFee,
            'remaining' => max(0, $total - $paid - $adminFee),
            'payments' => $payments,
        ];
    }
}
