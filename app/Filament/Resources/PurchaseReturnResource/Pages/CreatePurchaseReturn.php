<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\JournalService;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function afterCreate(): void
    {
        $retur = $this->record;

        if ($retur->status === 'PROCESSED') {
            $this->processReturn($retur);
        }
    }

    protected function processReturn($retur): void
    {
        foreach ($retur->details as $detail) {
            // Kurangi stok fisik (retur = barang keluar dari gudang)
            StockService::deductStock(
                $detail->product_id,
                1, // Default warehouse
                $detail->qty,
                $detail->price,
                'OUT',
                \App\Models\PurchaseReturn::class,
                $retur->id,
                'Retur ke supplier: ' . $retur->return_number,
                $retur->created_by
            );
        }

        // Journal: Debit Hutang Supplier, Kredit Persediaan
        $accountHutang = \App\Models\Account::where('code', '2-10001')->first();
        $accountPersediaan = \App\Models\Account::where('code', '1-20001')->first();

        if ($accountHutang && $accountPersediaan) {
            JournalService::createJournal(
                'Retur pembelian: ' . $retur->return_number,
                [
                    ['account_id' => $accountHutang->id, 'type' => 'DEBIT', 'amount' => $retur->total_amount, 'detail_description' => 'Pengurangan hutang supplier'],
                    ['account_id' => $accountPersediaan->id, 'type' => 'CREDIT', 'amount' => $retur->total_amount, 'detail_description' => 'Pengurangan persediaan'],
                ],
                \App\Models\PurchaseReturn::class,
                $retur->id,
                $retur->created_by
            );
        }
    }
}