<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\SalesInvoice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncAutoInvoices();
    }

    /**
     * Sinkronkan invoice auto-generated (yang belum PAID) mengikuti perubahan SO.
     */
    private function syncAutoInvoices(): void
    {
        $so = $this->record;

        foreach ($so->salesInvoices()->where('status', '!=', 'PAID')->get() as $invoice) {
            $invoice->update([
                'customer_id' => $so->customer_id,
                'total'       => $so->total_amount,
                'notes'       => 'Auto-generated invoice untuk SO: ' . $so->so_number,
            ]);

            $invoice->details()->delete();

            foreach ($so->details as $d) {
                $invoice->details()->create([
                    'product_id' => $d->product_id,
                    'qty'        => $d->qty,
                    'price'      => $d->unit_price,
                    'subtotal'   => $d->subtotal,
                ]);
            }
        }
    }
}
