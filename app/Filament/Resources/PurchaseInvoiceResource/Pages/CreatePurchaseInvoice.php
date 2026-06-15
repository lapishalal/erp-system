<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Services\JournalService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function afterCreate(): void
    {
        $invoice = $this->record;
        $invoice->refresh();

        $total = $invoice->total;

        // Journal: Debit Persediaan, Kredit Hutang Supplier
        $accountPersediaan = \App\Models\Account::where('code', '1-20001')->first();
        $accountHutang = \App\Models\Account::where('code', '2-10001')->first();

        if ($accountPersediaan && $accountHutang) {
            JournalService::createJournal(
                'Pembelian barang dagang: ' . $invoice->invoice_number,
                [
                    ['account_id' => $accountPersediaan->id, 'type' => 'DEBIT', 'amount' => $total, 'detail_description' => 'Pembelian dari supplier'],
                    ['account_id' => $accountHutang->id, 'type' => 'CREDIT', 'amount' => $total, 'detail_description' => 'Hutang ke supplier'],
                ],
                \App\Models\PurchaseInvoice::class,
                $invoice->id,
                $invoice->created_by
            );
        }
    }
}