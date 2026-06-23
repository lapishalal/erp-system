<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\JournalService;
use App\Services\StockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\PurchaseReturn;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // Capture status SEBELUM form di-save
        $originalStatus = $this->record->getOriginal('status');

        // Panggil parent save
        parent::save($shouldRedirect, $shouldSendSavedNotification);

        // Setelah parent save selesai, cek apakah status berubah ke PROCESSED
        if ($this->record->status === 'PROCESSED' && $originalStatus !== 'PROCESSED') {
            try {
                PurchaseReturn::processReturn($this->record->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('PurchaseReturn process error: ' . $e->getMessage());
                Notification::make()
                    ->title('Error saat memproses retur')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        // Cek apakah status berubah dari PROCESSED ke CANCEL
        if ($this->record->status === 'CANCEL' && $originalStatus === 'PROCESSED') {
            try {
                PurchaseReturn::cancelReturn($this->record->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('PurchaseReturn cancel error: ' . $e->getMessage());
                Notification::make()
                    ->title('Error saat membatalkan retur')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    protected function processReturn($retur): void
    {
        foreach ($retur->details as $detail) {
            StockService::deductStock(
                $detail->product_id,
                1,
                $detail->qty,
                $detail->price,
                'OUT',
                \App\Models\PurchaseReturn::class,
                $retur->id,
                'Retur ke supplier: ' . $retur->return_number,
                $retur->created_by
            );
        }

        $accountHutang = \App\Models\Account::where('code', '2-10001')->first();
        $accountPersediaan = \App\Models\Account::where('code', '1-20001')->first();

        if ($accountHutang && $accountPersediaan) {
            JournalService::createJournal(
                'Retur pembelian: ' . $retur->return_number,
                [
                    ['account_id' => $accountHutang->id, 'type' => 'DEBIT', 'amount' => $retur->total_amount, 'detail_description' => 'Pengurangan hutang supplier'],
                    ['account_id' => $accountPersediaan->id, 'type' => 'CREDIT', 'amount' => $retur->total_amount, 'detail_description' => 'Pengurangan persediaan'],
                ],
                \App\Models\PurchaseReturn::class,
                $retur->id,
                $retur->created_by
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}