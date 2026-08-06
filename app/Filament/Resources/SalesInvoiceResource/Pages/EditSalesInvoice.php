<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesOrder;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['total'] = $this->resolveTotal($data);

        return $data;
    }

    private function resolveTotal(array $data): float
    {
        if (!empty($data['so_id'])) {
            $so = SalesOrder::find($data['so_id']);
            if ($so) {
                return (float) $so->total_amount;
            }
        }

        $total = 0;
        foreach ($data['details'] ?? [] as $item) {
            $total += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
        }

        return $total;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
