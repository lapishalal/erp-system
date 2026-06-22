<?php

namespace App\Filament\Resources\MarketplaceConnectionResource\Pages;

use App\Filament\Resources\MarketplaceConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceConnection extends CreateRecord
{
    protected static string $resource = MarketplaceConnectionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}