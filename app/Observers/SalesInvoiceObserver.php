<?php

namespace App\Observers;

use App\Models\SalesInvoice;
use App\Services\JournalService;

class SalesInvoiceObserver
{
    public function created(SalesInvoice $salesInvoice): void
    {
        $totalHpp = 0;

        foreach ($salesInvoice->details as $detail) {
            $product = \App\Models\Product::find($detail->product_id);
            $costPrice = $product->last_buy_price ?? 0;
            $totalHpp += ($costPrice * $detail->qty);
        }

        JournalService::journalSalesInvoice($salesInvoice->total, $totalHpp, $salesInvoice->created_by ?? auth()->id());
    }
    
    public function updating(SalesInvoice $invoice): void
    {
        if ($invoice->isDirty('status') && $invoice->status === 'POSTED') {
            $invoice->posted_at = now();
        }
    }
}