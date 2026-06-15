<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Periode')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('period_name')->label('Periode'),
                        TextEntry::make('cutoff_date')->label('Cut-off')->date('d M Y'),
                        TextEntry::make('payment_date')->label('Tanggal Bayar')->date('d M Y'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'DRAFT' => 'gray',
                                'PROCESSED' => 'warning',
                                'PAID' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('payrolls_count')->label('Jumlah Karyawan'),
                        TextEntry::make('total_net_salary')->label('Total Gaji Bersih')->money('IDR'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === 'DRAFT'),
        ];
    }
}