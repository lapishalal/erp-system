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
                $total = 0;

                foreach ($so->details as $d) {
                    $qty = $d->qty ?? 0;
                    $price = $d->unit_price ?? 0;
                    $subtotal = $qty * $price;

                    $details[] = [
                        'product_id' => $d->product_id,
                        'qty'        => $qty,
                        'price'      => $price,
                        'subtotal'   => $subtotal,
                    ];

                    $total += $subtotal;
                }

                $this->form->fill([
                    'so_id'        => $so->id,
                    'customer_id'  => $so->customer_id,
                    'details'      => $details,
                    'total'        => $total,
                    'paid_amount'  => 0,
                    'status'       => 'UNPAID',
                    'due_date'     => now()->addDays(30),
                ]);
                return;
            }
        }

        parent::fillForm();
    }
}