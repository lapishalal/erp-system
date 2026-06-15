<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Audit')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                        TextEntry::make('user.name')->label('User')->placeholder('System'),
                        TextEntry::make('action')
                            ->label('Aksi')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'created' => 'Dibuat',
                                'updated' => 'Diubah',
                                'deleted' => 'Dihapus',
                                'restored' => 'Dikembalikan',
                                default => $state,
                            }),
                        TextEntry::make('auditable_type')
                            ->label('Model')
                            ->formatStateUsing(fn ($state) => class_basename($state)),
                        TextEntry::make('auditable_id')->label('ID Record'),
                        TextEntry::make('ip_address')->label('IP Address'),
                        TextEntry::make('url')->label('URL Endpoint'),
                    ]),

                Section::make('Data Lama (Old Values)')
                    ->visible(fn (AuditLog $record): bool => !empty($record->old_values))
                    ->schema([
                        TextEntry::make('old_values')
                            ->label(false)
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return '-';
                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            })
                            ->prose()
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm bg-gray-50 p-4 rounded-lg']),
                    ]),

                Section::make('Data Baru (New Values)')
                    ->visible(fn (AuditLog $record): bool => !empty($record->new_values))
                    ->schema([
                        TextEntry::make('new_values')
                            ->label(false)
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return '-';
                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            })
                            ->prose()
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm bg-gray-50 p-4 rounded-lg']),
                    ]),
            ]);
    }
}