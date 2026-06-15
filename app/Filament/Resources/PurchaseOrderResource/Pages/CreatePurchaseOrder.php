<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function afterCreate(): void
    {
        $po = $this->record;
        foreach ($po->details as $detail) {
            $detail->remaining_qty = $detail->qty;
            $detail->received_qty = 0;
            $detail->save();
        }
    }
}