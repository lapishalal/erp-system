<?php
// app/Services/SalesOrderTimelineService.php

namespace App\Services;

use App\Models\SalesOrder;
use Carbon\Carbon;

class SalesOrderTimelineService
{
    public function generate(SalesOrder $so): array
    {
        $stages = [
            $this->soStage($so),
            $this->doStage($so),
            $this->invoiceStage($so),
            $this->paymentStage($so),
        ];

        $progress = $this->calculateProgress($stages);
        $isOverdue = $this->checkOverdue($so);

        return [
            'stages' => $stages,
            'progress_percent' => $progress,
            'is_overdue' => $isOverdue,
            'current_stage' => collect($stages)->firstWhere('status', 'current')['label'] ?? 'Selesai',
            'quick_actions' => $this->getQuickActions($so),
        ];
    }

    private function soStage(SalesOrder $so): array
    {
        // PERBAIKAN 1: tambah 'COMPLETE' => 'completed'
        $status = match($so->status) {
            'APPROVED', 'COMPLETE' => 'completed',
            'PENDING' => 'current',
            'CANCELLED' => 'failed',
            default => 'pending',
        };

        return [
            'key' => 'so',
            'label' => 'Sales Order',
            'status' => $status,
            'date' => $so->approved_at ?? $so->created_at,
            'detail' => "SO #{$so->so_number}",
            'badge' => $so->status,
            'icon' => 'heroicon-o-clipboard-document-list',
            'meta' => [
                'customer' => $so->customer->name ?? '-',
                'total' => 'Rp ' . number_format($so->total_amount ?? 0),
            ]
        ];
    }

    private function doStage(SalesOrder $so): array
    {
        $dos = $so->deliveryOrders;
        
        if ($dos->isEmpty()) {
            return [
                'key' => 'do',
                'label' => 'Delivery',
                'status' => 'pending',
                'date' => null,
                'detail' => 'Belum ada DO',
                'badge' => 'Pending',
                'icon' => 'heroicon-o-truck',
                'meta' => ['completed' => 0, 'total' => 0],
            ];
        }

        $delivered = $dos->where('status', 'DELIVERED')->count();
        $total = $dos->count();
        $allDelivered = $delivered === $total;

        $status = $allDelivered ? 'completed' : ($delivered > 0 ? 'current' : 'pending');
        $lastDelivered = $dos->where('status', 'DELIVERED')->sortByDesc('delivered_at')->first();

        return [
            'key' => 'do',
            'label' => 'Delivery',
            'status' => $status,
            'date' => $lastDelivered?->delivered_at,
            'detail' => "DO: {$delivered}/{$total} delivered",
            'badge' => $allDelivered ? 'Delivered' : 'In Progress',
            'icon' => 'heroicon-o-truck',
            'meta' => [
                'completed' => $delivered,
                'total' => $total,
                'do_numbers' => $dos->pluck('do_number')->implode(', '),
            ]
        ];
    }

    private function invoiceStage(SalesOrder $so): array
    {
        $invoices = $so->salesInvoices;
        
        if ($invoices->isEmpty()) {
            return [
                'key' => 'invoice',
                'label' => 'Invoice',
                'status' => 'pending',
                'date' => null,
                'detail' => 'Belum ada invoice',
                'badge' => 'Pending',
                'icon' => 'heroicon-o-document-text',
                'meta' => ['completed' => 0, 'total' => 0],
            ];
        }

        $posted = $invoices->whereIn('status', ['POSTED', 'PAID'])->count();
        $total = $invoices->count();
        $allPosted = $posted === $total;

        $status = $allPosted ? 'completed' : ($posted > 0 ? 'current' : 'pending');
        $lastInvoice = $invoices->whereIn('status', ['POSTED', 'PAID'])->sortByDesc('posted_at')->first();

        return [
            'key' => 'invoice',
            'label' => 'Invoice',
            'status' => $status,
            'date' => $lastInvoice?->posted_at,
            'detail' => $lastInvoice ? "INV #{$lastInvoice->invoice_number}" : 'Draft',
            'badge' => $allPosted ? 'Posted' : 'Draft',
            'icon' => 'heroicon-o-document-text',
            'meta' => [
                'completed' => $posted,
                'total' => $total,
                'amount' => 'Rp ' . number_format($lastInvoice?->total ?? 0),
                'due_date' => $lastInvoice?->due_date?->format('d M Y'),
            ]
        ];
    }

    private function paymentStage(SalesOrder $so): array
    {
        $invoice = $so->salesInvoices->whereIn('status', ['POSTED', 'PAID'])->first();
        
        if (!$invoice) {
            return [
                'key' => 'payment',
                'label' => 'Payment',
                'status' => 'pending',
                'date' => null,
                'detail' => 'Menunggu invoice',
                'badge' => 'Pending',
                'icon' => 'heroicon-o-banknotes',
                'meta' => ['paid' => 0, 'total' => 0, 'percent' => 0],
            ];
        }

        $totalAmount = $invoice->total;
        
        if ($invoice->status === 'PAID') {
            return [
                'key' => 'payment',
                'label' => 'Payment',
                'status' => 'completed',
                'date' => $invoice->updated_at,
                'detail' => 'LUNAS (POS)',
                'badge' => 'Lunas',
                'icon' => 'heroicon-o-banknotes',
                'meta' => [
                    'paid' => $totalAmount,
                    'total' => $totalAmount,
                    'percent' => 100,
                    'remaining' => 0,
                ]
            ];
        }

        $totalPaid = $invoice->cashIns->sum('amount');
        $percent = $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100, 2) : 0;

        $status = match(true) {
            $totalPaid >= $totalAmount => 'completed',
            $totalPaid > 0 => 'current',
            default => 'pending',
        };

        $detail = match($status) {
            'completed' => 'LUNAS',
            'current' => "Partial: Rp " . number_format($totalPaid) . ' / ' . number_format($totalAmount),
            default => 'Belum dibayar',
        };

        return [
            'key' => 'payment',
            'label' => 'Payment',
            'status' => $status,
            'date' => $invoice->cashIns->sortByDesc('created_at')->first()?->created_at,
            'detail' => $detail,
            'badge' => $status === 'completed' ? 'Lunas' : ($status === 'current' ? 'Partial' : 'Unpaid'),
            'icon' => 'heroicon-o-banknotes',
            'meta' => [
                'paid' => $totalPaid,
                'total' => $totalAmount,
                'percent' => $percent,
                'remaining' => $totalAmount - $totalPaid,
            ]
        ];
    }

    private function calculateProgress(array $stages): float
    {
        $completed = collect($stages)->where('status', 'completed')->count();
        $total = count($stages);
        
        if ($total === 0) return 0;
        
        // PERBAIKAN 2: formula progress yang benar
        return ($completed / $total) * 100;
    }

    private function checkOverdue(SalesOrder $so): bool
    {
        $invoice = $so->salesInvoices->whereIn('status', ['POSTED', 'PAID'])->first();
        if (!$invoice || !$invoice->due_date) return false;
        
        if ($invoice->status === 'PAID') return false;
        
        return $invoice->due_date < Carbon::now() 
            && $invoice->cashIns->sum('amount') < $invoice->total;
    }

    private function getQuickActions(SalesOrder $so): array
    {
        $actions = [];
        $dos = $so->deliveryOrders;

        if ($so->status === 'APPROVED' && $dos->isEmpty()) {
            $actions[] = [
                'label' => 'Buat DO',
                'icon' => 'heroicon-o-truck',
                'url' => route('filament.admin.resources.delivery-orders.create', ['so_id' => $so->id]),
                'color' => 'primary',
                'visible' => true,
            ];
        }

        $hasDeliveredDO = $dos->contains(fn($d) => $d->status === 'DELIVERED');
        $hasNoInvoice = $so->salesInvoices->whereIn('status', ['POSTED', 'PAID'])->isEmpty();
        
        if ($hasDeliveredDO && $hasNoInvoice) {
            $actions[] = [
                'label' => 'Buat Invoice',
                'icon' => 'heroicon-o-document-text',
                'url' => route('filament.admin.resources.sales-invoices.create', ['so_id' => $so->id]),
                'color' => 'success',
                'visible' => true,
            ];
        }

        $invoice = $so->salesInvoices->whereIn('status', ['POSTED', 'PAID'])->first();
        if ($invoice && $invoice->status !== 'PAID' && $invoice->cashIns->sum('amount') < $invoice->total) {
            $actions[] = [
                'label' => 'Input Pembayaran',
                'icon' => 'heroicon-o-banknotes',
                'url' => route('filament.admin.resources.cash-ins.create', ['invoice_id' => $invoice->id]),
                'color' => 'warning',
                'visible' => true,
            ];
        }

        return $actions;
    }
}