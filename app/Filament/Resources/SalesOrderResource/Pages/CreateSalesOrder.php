<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\SalesOrderDetail;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function afterCreate(): void
    {
        $so = $this->record;

        foreach ($so->details as $detail) {
            $detail->remaining_qty = $detail->qty;
            $detail->delivered_qty = 0;
            $detail->save();

            StockService::addOutstanding($detail->product_id, 1, $detail->qty);
        }

        if ($so->status === 'OPEN') {
            $so->status = 'OPEN';
            $so->save();
        }
    }
}