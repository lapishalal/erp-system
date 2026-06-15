<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPosTransaction extends ViewRecord
{
    protected static string $resource = PosTransactionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('transaction_number')->label('No. Transaksi'),
                TextEntry::make('date')->label('Tanggal')->dateTime('d M Y H:i'),
                TextEntry::make('creator.name')->label('Kasir')->placeholder('-'),
                TextEntry::make('customer.name')->label('Customer')->placeholder('Walk-in'),
                TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                TextEntry::make('discount')->label('Diskon')->money('IDR'),
                TextEntry::make('total')->label('Total')->money('IDR')->weight('bold'),
                TextEntry::make('paid_amount')->label('Bayar')->money('IDR'),
                TextEntry::make('change_amount')->label('Kembalian')->money('IDR'),
                TextEntry::make('payment_method')->label('Metode Bayar')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'CASH' => 'Tunai',
                        'DEBIT' => 'Debit',
                        'QRIS' => 'QRIS',
                        'TRANSFER' => 'Transfer',
                        default => $state,
                    }),
            ]);
    }
}