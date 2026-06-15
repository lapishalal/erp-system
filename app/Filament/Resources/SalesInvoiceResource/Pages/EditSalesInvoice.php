<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $total = 0;
        foreach ($data['details'] ?? [] as $item) {
            $total += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
        }
        $data['total'] = $total;
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}