<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    public function getHeaderActions(): array
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
                Section::make('Informasi Jurnal')
                    ->schema([
                        TextEntry::make('date')
                            ->label('Tanggal')
                            ->date('d M Y'),

                        TextEntry::make('description')
                            ->label('Deskripsi'),

                        TextEntry::make('total_debit')
                            ->label('Total Debit')
                            ->money('IDR'),

                        TextEntry::make('total_credit')
                            ->label('Total Kredit')
                            ->money('IDR'),

                        // ✅ FIX: Ganti boolean() dengan badge + color
                        TextEntry::make('is_posted')
                            ->label('Posted')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Posted' : 'Draft'),

                        TextEntry::make('creator.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Detail Jurnal')
                    ->schema([
                        TextEntry::make('details')
                            ->label(false)
                            ->listWithLineBreaks()
                            ->formatStateUsing(function (JournalEntry $record): string {
                                $lines = [];
                                foreach ($record->details as $d) {
                                    $account = $d->account->code . ' - ' . $d->account->name;
                                    $debit = $d->debit > 0 ? 'Rp ' . number_format($d->debit, 0, ',', '.') : '-';
                                    $credit = $d->credit > 0 ? 'Rp ' . number_format($d->credit, 0, ',', '.') : '-';
                                    $lines[] = "{$account} | Debit: {$debit} | Kredit: {$credit}";
                                }
                                return implode("\n", $lines);
                            }),
                    ])
                    ->collapsible(),
            ]);
    }
}