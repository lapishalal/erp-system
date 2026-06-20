<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\DeliveryOrder;
use App\Models\SalesInvoice;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Timeline';
    }

    public function getContent(): string
    {
        $so = $this->record;
        $doList = DeliveryOrder::where('so_id', $so->id)->get();
        $invoiceList = SalesInvoice::where('so_id', $so->id)->get();

        $html = '<div class="space-y-6">';
        $html .= '<div class="relative border-l-2 border-primary-500 ml-4 space-y-8">';

        // SO Created
        $html .= '<div class="relative pl-8">';
        $html .= '<div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-primary-500 border-4 border-white shadow"></div>';
        $html .= '<div class="bg-white rounded-lg shadow p-4 border">';
        $html .= '<div class="flex items-center gap-2 text-sm font-semibold text-primary-600"><span>✅</span> SO Dibuat</div>';
        $html .= '<p class="text-sm text-gray-600 mt-1">' . e($so->so_number) . ' | ' . $so->date->format('d M Y') . '</p>';
        $html .= '<p class="text-sm text-gray-500">Customer: ' . e($so->customer?->name ?? '-') . '</p>';
        $html .= '<p class="text-sm text-gray-500">Total: Rp ' . number_format($so->total_amount, 0, ',', '.') . '</p>';
        $html .= '</div></div>';

        // DO
        foreach ($doList as $do) {
            $html .= '<div class="relative pl-8">';
            $html .= '<div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-info-500 border-4 border-white shadow"></div>';
            $html .= '<div class="bg-white rounded-lg shadow p-4 border">';
            $html .= '<div class="flex items-center gap-2 text-sm font-semibold text-info-600"><span>🚚</span> Surat Jalan</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">' . e($do->do_number) . ' | ' . $do->date->format('d M Y') . '</p>';
            $html .= '<p class="text-sm text-gray-500">Status: ' . e($do->status) . ' | Qty: ' . $do->total_qty . '</p>';
            $html .= '<a href="' . url('/admin/delivery-orders/' . $do->id . '/edit') . '" class="text-xs text-primary-600 hover:underline">Lihat DO →</a>';
            $html .= '</div></div>';
        }

        // Invoice
        foreach ($invoiceList as $inv) {
            $html .= '<div class="relative pl-8">';
            $html .= '<div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-warning-500 border-4 border-white shadow"></div>';
            $html .= '<div class="bg-white rounded-lg shadow p-4 border">';
            $html .= '<div class="flex items-center gap-2 text-sm font-semibold text-warning-600"><span>🧾</span> Faktur</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">' . e($inv->invoice_number) . ' | Jatuh Tempo: ' . $inv->due_date->format('d M Y') . '</p>';
            $html .= '<p class="text-sm text-gray-500">Total: Rp ' . number_format($inv->total, 0, ',', '.') . ' | Status: ' . e($inv->status) . '</p>';
            $html .= '<a href="' . url('/admin/sales-invoices/' . $inv->id . '/edit') . '" class="text-xs text-primary-600 hover:underline">Lihat Invoice →</a>';
            $html .= '</div></div>';
        }

        // Payment
        $paidInvoices = $invoiceList->where('status', 'PAID');
        if ($paidInvoices->isNotEmpty()) {
            foreach ($paidInvoices as $inv) {
                $html .= '<div class="relative pl-8">';
                $html .= '<div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-success-500 border-4 border-white shadow"></div>';
                $html .= '<div class="bg-white rounded-lg shadow p-4 border">';
                $html .= '<div class="flex items-center gap-2 text-sm font-semibold text-success-600"><span>💰</span> Lunas</div>';
                $html .= '<p class="text-sm text-gray-600 mt-1">Faktur ' . e($inv->invoice_number) . ' telah dibayar lunas</p>';
                $html .= '<p class="text-sm text-gray-500">Total: Rp ' . number_format($inv->total, 0, ',', '.') . '</p>';
                $html .= '</div></div>';
            }
        } else {
            $html .= '<div class="relative pl-8">';
            $html .= '<div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-gray-300 border-4 border-white shadow"></div>';
            $html .= '<div class="bg-gray-50 rounded-lg shadow p-4 border border-dashed">';
            $html .= '<div class="flex items-center gap-2 text-sm font-semibold text-gray-400"><span>💰</span> Pembayaran</div>';
            $html .= '<p class="text-sm text-gray-400 mt-1">Belum ada pembayaran lunas</p>';
            $html .= '</div></div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}