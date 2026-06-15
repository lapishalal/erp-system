<?php

namespace App\Filament\Resources\ProfitLossResource\Pages;

use App\Filament\Resources\ProfitLossResource;
use Filament\Resources\Pages\ListRecords;

class ListProfitLoss extends ListRecords
{
    protected static string $resource = ProfitLossResource::class;

    public function getTableRecordKey($record): string
    {
        return (string) ($record->account_id ?? $record->id ?? uniqid());
    }
}