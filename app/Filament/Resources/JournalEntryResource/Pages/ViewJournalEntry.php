<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => !$this->record->is_posted),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('date')->label('Tanggal'),
                TextEntry::make('description')->label('Deskripsi'),
                TextEntry::make('total_debit')->label('Total Debit')->money('IDR'),
                TextEntry::make('total_credit')->label('Total Kredit')->money('IDR'),
                TextEntry::make('is_posted')->label('Posted')->boolean(),
                TextEntry::make('creator.name')->label('Dibuat Oleh'),
            ]);
    }
}