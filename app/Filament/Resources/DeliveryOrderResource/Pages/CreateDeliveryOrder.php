<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function fillForm(): void
    {
        $soId = request()->query('so_id');

        if ($soId) {
            $so = SalesOrder::with(['details.product'])->find($soId);

            if ($so) {
                $details = [];

                foreach ($so->details as $d) {
                    $remaining = $d->remaining_qty ?? 0;

                    if ($remaining > 0) {
                        $details[] = [
                            'product_id' => $d->product_id,
                            'max_qty'    => $remaining,
                            'qty'        => $remaining,
                        ];
                    }
                }

                if (count($details) > 0) {
                    $this->form->fill([
                        'so_id'       => $so->id,
                        'customer_id' => $so->customer_id,
                        'warehouse_id' => Warehouse::first()?->id,
                        'details'     => $details,
                    ]);
                    return;
                }
            }
        }

        parent::fillForm();
    }
}