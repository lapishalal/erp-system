<?php

namespace App\Filament\Resources\CashInResource\Pages;

use App\Filament\Resources\CashInResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashIns extends ListRecords
{
    protected static string $resource = CashInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}