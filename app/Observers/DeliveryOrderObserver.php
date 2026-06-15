<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\Product;
use App\Models\ProductBuyPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\JournalService;
use App\Services\StockService;

class DeliveryOrderObserver
{
    public function created(DeliveryOrder $deliveryOrder): void
    {
        // When DO is created (draft), reserve outstanding
        foreach ($deliveryOrder->details as $detail) {
            StockService::addOutstanding($detail->product_id, 1, $detail->qty);
        }
    }

    public function updated(DeliveryOrder $deliveryOrder): void
    {
        if ($deliveryOrder->isDirty('status')) {
            $oldStatus = $deliveryOrder->getOriginal('status');
            $newStatus = $deliveryOrder->status;

            // Draft -> Delivered: deduct physical stock, release outstanding
            if ($oldStatus === 'DRAFT' && $newStatus === 'DELIVERED') {
                $this->processDelivery($deliveryOrder);
            }

            // Delivered -> Draft: reverse (rare case)
            if ($oldStatus === 'DELIVERED' && $newStatus === 'DRAFT') {
                $this->reverseDelivery($deliveryOrder);
            }
        }
    }

    protected function processDelivery(DeliveryOrder $deliveryOrder): void
    {
        $totalHpp = 0;
        $totalSales = 0;

        foreach ($deliveryOrder->details as $detail) {
            // Deduct outstanding first
            StockService::deductOutstanding($detail->product_id, 1, $detail->qty);

            // Get cost price (FIFO - last buy price or product last_buy_price)
            $product = Product::find($detail->product_id);
            $costPrice = $product->last_buy_price ?? 0;

            // Deduct physical stock
            StockService::deductStock(
                $detail->product_id,
                1,
                $detail->qty,
                $costPrice,
                'OUT',
                DeliveryOrder::class,
                $deliveryOrder->id,
                'Surat Jalan ' . $deliveryOrder->do_number,
                $deliveryOrder->created_by
            );

            $totalHpp += ($costPrice * $detail->qty);

            // Find SO detail to get sale price
            $soDetail = SalesOrderDetail::where('so_id', $deliveryOrder->so_id)
                ->where('product_id', $detail->product_id)
                ->first();

            if ($soDetail) {
                $totalSales += ($soDetail->unit_price * $detail->qty);

                // Update delivered qty
                $soDetail->delivered_qty += $detail->qty;
                $soDetail->remaining_qty = max(0, $soDetail->qty - $soDetail->delivered_qty);
                $soDetail->save();
            }
        }

        // Update SO status
        $this->updateSalesOrderStatus($deliveryOrder->so_id);

        // Auto journal HPP if you want immediately, or wait until invoice
        // For now, we journal when invoice is created
    }

    protected function reverseDelivery(DeliveryOrder $deliveryOrder): void
    {
        foreach ($deliveryOrder->details as $detail) {
            StockService::addOutstanding($detail->product_id, 1, $detail->qty);

            $product = Product::find($detail->product_id);
            $costPrice = $product->last_buy_price ?? 0;

            StockService::addStock(
                $detail->product_id,
                1,
                $detail->qty,
                $costPrice,
                'IN',
                DeliveryOrder::class,
                $deliveryOrder->id,
                'Reverse Surat Jalan ' . $deliveryOrder->do_number,
                $deliveryOrder->created_by
            );

            $soDetail = SalesOrderDetail::where('so_id', $deliveryOrder->so_id)
                ->where('product_id', $detail->product_id)
                ->first();

            if ($soDetail) {
                $soDetail->delivered_qty = max(0, $soDetail->delivered_qty - $detail->qty);
                $soDetail->remaining_qty = $soDetail->qty - $soDetail->delivered_qty;
                $soDetail->save();
            }
        }

        $this->updateSalesOrderStatus($deliveryOrder->so_id);
    }

    protected function updateSalesOrderStatus(int $soId): void
    {
        $so = SalesOrder::with('details')->find($soId);
        if (!$so) return;

        $totalQty = $so->details->sum('qty');
        $totalDelivered = $so->details->sum('delivered_qty');

        if ($totalDelivered >= $totalQty) {
            $so->status = 'COMPLETE';
        } elseif ($totalDelivered > 0) {
            $so->status = 'PARTIAL';
        } else {
            $so->status = 'OPEN';
        }

        $so->save();
    }
}