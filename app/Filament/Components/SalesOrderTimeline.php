<?php

namespace App\Filament\Components;

use App\Models\SalesOrder;
use Filament\Infolists\Components\Entry;

class SalesOrderTimeline extends Entry
{
    protected string $view = 'filament.components.sales-order-timeline';

    public function getState(): array
    {
        $record = $this->getRecord(); // SalesOrder model
        
        return [
            'stages' => [
                [
                    'label' => 'Sales Order',
                    'status' => $record->status === 'APPROVED' ? 'completed' : 
                               ($record->status === 'PENDING' ? 'current' : 'pending'),
                    'date' => $record->approved_at ?? $record->created_at,
                    'detail' => "SO #{$record->order_number}",
                    'icon' => 'heroicon-o-clipboard-document-list',
                ],
                [
                    'label' => 'Delivery',
                    'status' => $this->getDeliveryStatus($record),
                    'date' => $record->deliveryOrders->first()?->delivered_at,
                    'detail' => $this->getDeliveryDetail($record),
                    'icon' => 'heroicon-o-truck',
                ],
                [
                    'label' => 'Invoice',
                    'status' => $this->getInvoiceStatus($record),
                    'date' => $record->salesInvoices->first()?->posted_at,
                    'detail' => $this->getInvoiceDetail($record),
                    'icon' => 'heroicon-o-document-text',
                ],
                [
                    'label' => 'Payment',
                    'status' => $this->getPaymentStatus($record),
                    'date' => $record->salesInvoices->first()?->cashIns->first()?->created_at,
                    'detail' => $this->getPaymentDetail($record),
                    'icon' => 'heroicon-o-banknotes',
                ],
            ]
        ];
    }

    private function getDeliveryStatus(SalesOrder $so): string
    {
        $do = $so->deliveryOrders;
        if ($do->isEmpty()) return 'pending';
        if ($do->every(fn($d) => $d->status === 'DELIVERED')) return 'completed';
        if ($do->contains(fn($d) => $d->status === 'SHIPPED')) return 'current';
        return 'pending';
    }

    private function getInvoiceStatus(SalesOrder $so): string
    {
        $inv = $so->salesInvoices;
        if ($inv->isEmpty()) return 'pending';
        if ($inv->every(fn($i) => $i->status === 'POSTED')) return 'completed';
        if ($inv->contains(fn($i) => $i->status === 'DRAFT')) return 'current';
        return 'pending';
    }

    private function getPaymentStatus(SalesOrder $so): string
    {
        $invoice = $so->salesInvoices->first();
        if (!$invoice) return 'pending';
        
        $totalPaid = $invoice->cashIns->sum('amount');
        if ($totalPaid >= $invoice->total_amount) return 'completed';
        if ($totalPaid > 0) return 'current'; // partial payment
        return 'pending';
    }

    private function getDeliveryDetail(SalesOrder $so): string
    {
        $do = $so->deliveryOrders;
        if ($do->isEmpty()) return 'Belum ada DO';
        $delivered = $do->where('status', 'DELIVERED')->count();
        $total = $do->count();
        return "DO: {$delivered}/{$total} delivered";
    }

    private function getInvoiceDetail(SalesOrder $so): string
    {
        $inv = $so->salesInvoices->first();
        if (!$inv) return 'Belum ada invoice';
        return "INV #{$inv->invoice_number} - Rp " . number_format($inv->total_amount);
    }

    private function getPaymentDetail(SalesOrder $so): string
    {
        $invoice = $so->salesInvoices->first();
        if (!$invoice) return 'Menunggu invoice';
        
        $totalPaid = $invoice->cashIns->sum('amount');
        $total = $invoice->total_amount;
        
        if ($totalPaid >= $total) return 'LUNAS';
        if ($totalPaid > 0) return 'Partial: Rp ' . number_format($totalPaid) . ' / ' . number_format($total);
        return 'Belum dibayar';
    }
}