<?php

namespace App\Filament\Resources\ProductStockResource\Pages;

use App\Filament\Resources\ProductStockResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProductStock extends ViewRecord
{
    protected static string $resource = ProductStockResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('product.name')->label('Barang'),
                TextEntry::make('product.code')->label('Kode'),
                TextEntry::make('warehouse.name')->label('Gudang'),
                TextEntry::make('physical_stock')->label('Stok Fisik'),
                TextEntry::make('outstanding_stock')->label('Outstanding (SO)'),
                TextEntry::make('available_stock')->label('Available'),
                TextEntry::make('product.min_stock')->label('Minimum Stok'),
                TextEntry::make('product.default_sale_price')->label('Harga Jual')->money('IDR'),
                TextEntry::make('product.last_buy_price')->label('HPP Terakhir')->money('IDR'),
            ]);
    }
}