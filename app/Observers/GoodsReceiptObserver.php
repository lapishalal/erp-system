<?php

namespace App\Observers;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductBuyPrice;
use App\Models\PurchaseOrderDetail;
use App\Services\JournalService;
use App\Services\StockService;

class GoodsReceiptObserver
{
    public function created(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->status === 'RECEIVED') {
            $this->processReceipt($goodsReceipt);
        }
    }

    public function updated(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->isDirty('status') 
            && $goodsReceipt->status === 'RECEIVED' 
            && $goodsReceipt->getOriginal('status') !== 'RECEIVED') {
            $this->processReceipt($goodsReceipt);
        }
    }

    protected function processReceipt(GoodsReceipt $goodsReceipt): void
    {
        $totalAmount = 0;
        $warehouseId = $goodsReceipt->warehouse_id ?? 1;

        // Reload details untuk pastikan data terbaru
        $goodsReceipt->load('details');

        foreach ($goodsReceipt->details as $detail) {
            if ($detail->qty <= 0) continue;

            // ✅ Add stock pakai qty (bukan subtotal)
            StockService::addStock(
                $detail->product_id,
                $warehouseId,
                $detail->qty,
                $detail->buy_price,
                'IN',
                GoodsReceipt::class,
                $goodsReceipt->id,
                'Penerimaan barang ' . $goodsReceipt->gr_number,
                $goodsReceipt->created_by
            );

            // Save buy price history
            ProductBuyPrice::create([
                'product_id' => $detail->product_id,
                'gr_id' => $goodsReceipt->id,
                'supplier_id' => $goodsReceipt->supplier_id,
                'buy_price' => $detail->buy_price,
                'qty' => $detail->qty,
                'date' => $goodsReceipt->date,
            ]);

            // Update product last buy price
            $product = Product::find($detail->product_id);
            if ($product) {
                $product->last_buy_price = $detail->buy_price;
                $product->save();
            }

            $totalAmount += $detail->subtotal;

            // Update PO detail received qty
            if ($goodsReceipt->po_id) {
                $poDetail = PurchaseOrderDetail::where('po_id', $goodsReceipt->po_id)
                    ->where('product_id', $detail->product_id)
                    ->first();

                if ($poDetail) {
                    $poDetail->received_qty += $detail->qty;
                    $poDetail->remaining_qty = max(0, $poDetail->qty - $poDetail->received_qty);
                    $poDetail->save();
                }
            }
        }

        // Update PO status
        if ($goodsReceipt->po_id) {
            $this->updatePurchaseOrderStatus($goodsReceipt->po_id);
        }

        // Save total amount to GR
        $goodsReceipt->total_amount = $totalAmount;
        $goodsReceipt->saveQuietly();

        // Auto journal
        JournalService::journalGoodsReceipt($totalAmount, $goodsReceipt->created_by);
    }

    protected function updatePurchaseOrderStatus(int $poId): void
    {
        $po = \App\Models\PurchaseOrder::with('details')->find($poId);
        if (!$po) return;

        $totalQty = $po->details->sum('qty');
        $totalReceived = $po->details->sum('received_qty');

        if ($totalReceived >= $totalQty) {
            $po->status = 'COMPLETE';
        } elseif ($totalReceived > 0) {
            $po->status = 'PARTIAL';
        } else {
            $po->status = 'ORDERED';
        }

        $po->save();
    }
}