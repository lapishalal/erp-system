<?php

namespace App\Filament\Resources\BalanceSheetResource\Pages;

use App\Filament\Resources\BalanceSheetResource;
use Filament\Resources\Pages\ListRecords;

class ListBalanceSheet extends ListRecords
{
    protected static string $resource = BalanceSheetResource::class;

    public function getTableRecordKey($record): string
    {
        return (string) ($record->account_id ?? $record->id ?? uniqid());
    }
}