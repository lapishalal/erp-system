<?php

namespace App\Filament\Actions;

use App\Services\MarketplaceDeliveryOrderCreator;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class CreateDeliveryOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'create_do';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Create DO')
            ->icon('heroicon-o-truck')
            ->color('success')
            ->visible(function (Model $record): bool {
                // Tampil untuk SEMUA SO yang OPEN dan belum punya DO
                return $record->status === 'OPEN'
                    && !\App\Models\DeliveryOrder::where('so_id', $record->id)->exists();
            })
            ->action(function (Model $record) {
                $creator = app(MarketplaceDeliveryOrderCreator::class);
                $do = $creator->createFromSalesOrder($record);

                if ($do) {
                    \Filament\Notifications\Notification::make()
                        ->title('Delivery Order Created')
                        ->body("DO Number: {$do->do_number}")
                        ->success()
                        ->send();
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('DO Already Exists')
                        ->warning()
                        ->send();
                }
            });
    }
}