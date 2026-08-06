<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function fillForm(): void
    {
        $soId = request()->query('so_id');

        if ($soId) {
            $so = SalesOrder::with(['details.product'])->find($soId);

            if ($so) {
                $details = [];

                foreach ($so->details as $d) {
                    $qty = $d->qty ?? 0;
                    $price = $d->unit_price ?? 0;

                    $details[] = [
                        'product_id' => $d->product_id,
                        'qty'        => $qty,
                        'price'      => $price,
                        'subtotal'   => $qty * $price,
                    ];
                }

                $this->form->fill([
                    'so_id'        => $so->id,
                    'customer_id'  => $so->customer_id,
                    'details'      => $details,
                    'total'        => (float) $so->total_amount,
                    'status'       => 'UNPAID',
                    'due_date'     => now()->addDays(30),
                ]);
                return;
            }
        }

        parent::fillForm();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['total'] = $this->resolveTotal($data);

        return $data;
    }

    private function resolveTotal(array $data): float
    {
        if (!empty($data['so_id'])) {
            $so = SalesOrder::find($data['so_id']);
            if ($so) {
                return (float) $so->total_amount;
            }
        }

        $total = 0;
        foreach ($data['details'] ?? [] as $item) {
            $total += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
        }

        return $total;
    }
}
