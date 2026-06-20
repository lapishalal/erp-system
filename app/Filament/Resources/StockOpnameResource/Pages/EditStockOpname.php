<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\StockOpnameResource;
use App\Exports\StockOpnameExport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Maatwebsite\Excel\Facades\Excel;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // =========================================================
            // EXPORT EXCEL DI HEADER HALAMAN EDIT
            // =========================================================
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(
                        new StockOpnameExport($this->record),
                        'stock-opname-' . $this->record->id . '-' . $this->record->opname_date->format('Ymd') . '.xlsx'
                    );
                }),
            
            Actions\DeleteAction::make(),
        ];
    }
}