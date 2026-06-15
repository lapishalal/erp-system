<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\JournalService;
use App\Services\StockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function afterSave(): void
    {
        $retur = $this->record;

        if ($this->record->wasChanged('status') && $retur->status === 'PROCESSED') {
            $this->processReturn($retur);
        }
    }

    protected function processReturn($retur): void
    {
        foreach ($retur->details as $detail) {
            StockService::deductStock(
                $detail->product_id,
                1,
                $detail->qty,
                $detail->price,
                'OUT',
                \App\Models\PurchaseReturn::class,
                $retur->id,
                'Retur ke supplier: ' . $retur->return_number,
                $retur->created_by
            );
        }

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}