<?php
// app/Filament/Infolists/Components/SalesOrderTimeline.php

namespace App\Filament\Infolists\Components;

use App\Services\SalesOrderTimelineService;
use Filament\Infolists\Components\Entry;

class SalesOrderTimeline extends Entry
{
    protected string $view = 'filament.infolists.components.sales-order-timeline';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function getState(): array
    {
        $record = $this->getRecord();
        $service = app(SalesOrderTimelineService::class);
        
        return $service->generate($record);
    }
}