<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\SalesInvoice;
use App\Models\SalesOrderDetail;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function afterCreate(): void
    {
        $so = $this->record;

        foreach ($so->details as $detail) {
            $detail->remaining_qty = $detail->qty;
            $detail->delivered_qty = 0;
            $detail->save();
        }

        if ($so->is_dropship) {
            // Dropship: paksa status OPEN, lalu buat PO + DO + Invoice otomatis
            $so->status = 'OPEN';
            $so->save();

            $this->autoCreatePurchaseOrder($so);
            $this->autoCreateDeliveryOrder($so);
            $this->autoCreateInvoice($so);
        } else {
            foreach ($so->details as $detail) {
                StockService::addOutstanding($detail->product_id, 1, $detail->qty);
            }

            if ($so->status === 'OPEN') {
                $so->status = 'OPEN';
                $so->save();
            }
        }
    }

    protected function autoCreatePurchaseOrder($so): void
    {
        $totalAmount = 0;
        $items = [];

        foreach ($so->details as $detail) {
            $product = Product::find($detail->product_id);
            $unitPrice = $product?->last_buy_price ?? 0;
            $subtotal = $detail->qty * $unitPrice;
            $totalAmount += $subtotal;

            $items[] = [
                'product_id' => $detail->product_id,
                'qty' => $detail->qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        $po = PurchaseOrder::create([
            'po_number' => $this->generateUniqueNumber('PO-DS-', PurchaseOrder::class, 'po_number'),
            'date' => now(),
            'supplier_id' => $so->dropship_supplier_id,
            'status' => 'ORDERED',
            'total_amount' => $totalAmount,
            'notes' => 'Auto-generated PO untuk dropship SO: ' . $so->so_number,
            'created_by' => auth()->id(),
        ]);

        foreach ($items as $item) {
            PurchaseOrderDetail::create(array_merge($item, ['po_id' => $po->id]));
        }
    }

    protected function autoCreateDeliveryOrder($so): void
    {
        $do = DeliveryOrder::create([
            'do_number' => $this->generateUniqueNumber('SJ-', DeliveryOrder::class, 'do_number'),
            'date' => now(),
            'so_id' => $so->id,
            'customer_id' => $so->customer_id,
            'warehouse_id' => null,
            'status' => 'DELIVERED',
            'is_dropship' => true,
            'total_qty' => $so->total_qty,
            'notes' => 'Auto-generated dropship DO untuk SO: ' . $so->so_number,
            'created_by' => auth()->id(),
        ]);

        foreach ($so->details as $detail) {
            $do->details()->create([
                'product_id' => $detail->product_id,
                'so_detail_id' => $detail->id,
                'qty' => $detail->qty,
                'notes' => 'Dropship',
            ]);
        }
    }

    protected function autoCreateInvoice($so): void
    {
        $invoice = SalesInvoice::create([
            'invoice_number' => $this->generateUniqueNumber('INV-', SalesInvoice::class, 'invoice_number'),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'so_id' => $so->id,
            'customer_id' => $so->customer_id,
            'total' => $so->total_amount,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'notes' => 'Auto-generated dropship invoice untuk SO: ' . $so->so_number,
        ]);

        foreach ($so->details as $d) {
            $invoice->details()->create([
                'product_id' => $d->product_id,
                'qty' => $d->qty,
                'price' => $d->unit_price,
                'subtotal' => $d->subtotal,
            ]);
        }
    }

    protected function generateUniqueNumber(string $prefix, string $model, string $field): string
    {
        do {
            $number = $prefix . date('Ymd') . '-' . rand(1000, 9999);
        } while ($model::query()->where($field, $number)->exists());

        return $number;
    }
}
