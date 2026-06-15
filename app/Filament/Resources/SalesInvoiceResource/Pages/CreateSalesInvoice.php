<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Services\JournalService;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $total = 0;
        foreach ($data['details'] ?? [] as $item) {
            $total += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
        }
        $data['total'] = $total;
        $data['paid_amount'] = $data['paid_amount'] ?? 0;
        return $data;
    }

    protected function afterCreate(): void
    {
        $invoice = $this->record;
        $invoice->refresh();

        $totalHpp = 0;
        foreach ($invoice->details as $detail) {
            $product = \App\Models\Product::find($detail->product_id);
            $costPrice = $product->last_buy_price ?? 0;
            $totalHpp += ($costPrice * $detail->qty);
        }

        JournalService::journalSalesInvoice($invoice->total, $totalHpp, $invoice->created_by ?? auth()->id());
    }
}