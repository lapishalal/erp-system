<?php

namespace App\Filament\Resources\MarketplaceConnectionResource\Pages;

use App\Filament\Resources\MarketplaceConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceConnection extends EditRecord
{
    protected static string $resource = MarketplaceConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}