<?php

namespace App\Filament\Resources\MarketplaceConnectionResource\Pages;

use App\Filament\Resources\MarketplaceConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceConnections extends ListRecords
{
    protected static string $resource = MarketplaceConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Koneksi API'),
        ];
    }
}