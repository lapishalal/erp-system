<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\JournalService;
use App\Jobs\SyncStockToMarketplace;
use Illuminate\Support\Facades\Log;

class DeliveryOrderObserver
{
    public function created(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->load('details');
        foreach ($deliveryOrder->details as $detail) {
            SyncStockToMarketplace::dispatch(
                $detail->product_id,
                $deliveryOrder->tenant_id
            );
        }
    }

    public function updating(DeliveryOrder $do): void
    {
        if ($do->isDirty('status')) {
            match($do->status) {
                'SHIPPED' => $do->shipped_at = now(),
                'DELIVERED' => $do->delivered_at = now(),
                default => null,
            };
        }
    }

    public function updated(DeliveryOrder $deliveryOrder): void
    {
        if ($deliveryOrder->isDirty('status')) {
            $oldStatus = $deliveryOrder->getOriginal('status');
            $newStatus = $deliveryOrder->status;

            if ($oldStatus === 'DRAFT' && $newStatus === 'DELIVERED') {
                $this->processDelivery($deliveryOrder);
            }

            if ($oldStatus === 'DELIVERED' && in_array($newStatus, ['DRAFT', 'CANCEL'])) {
                $this->reverseDelivery($deliveryOrder);
            }
        }
    }

    public function deleted(DeliveryOrder $deliveryOrder): void
    {
        $this->deleteJournals($deliveryOrder);
    }

    protected function processDelivery(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder = $deliveryOrder->fresh(['details']);

        $totalHpp = 0;
        $totalSales = 0;

        Log::info('DO processDelivery started', [
            'do_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
            'so_id' => $deliveryOrder->so_id,
            'details_count' => $deliveryOrder->details->count(),
        ]);

        foreach ($deliveryOrder->details as $detail) {
            $product = Product::find($detail->product_id);
            $costPrice = $product->last_buy_price ?? 0;
            $totalHpp += ($costPrice * $detail->qty);

            $soDetail = null;
            if ($detail->so_detail_id) {
                $soDetail = SalesOrderDetail::find($detail->so_detail_id);
            } elseif ($deliveryOrder->so_id) {
                $soDetail = SalesOrderDetail::where('so_id', $deliveryOrder->so_id)
                    ->where('product_id', $detail->product_id)
                    ->first();
            }

            Log::info('DO detail processing', [
                'do_detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'so_detail_id' => $detail->so_detail_id,
                'so_detail_found' => $soDetail ? 'YES' : 'NO',
                'unit_price' => $soDetail?->unit_price,
                'qty' => $detail->qty,
            ]);

            if ($soDetail) {
                $totalSales += ($soDetail->unit_price * $detail->qty);

                $soDetail->delivered_qty += $detail->qty;
                $soDetail->remaining_qty = max(0, $soDetail->qty - $soDetail->delivered_qty);
                $soDetail->saveQuietly();
            }
        }

        $this->updateSalesOrderStatus($deliveryOrder->so_id);

        Log::info('DO totals calculated', [
            'do_id' => $deliveryOrder->id,
            'total_sales' => $totalSales,
            'total_hpp' => $totalHpp,
        ]);

        try {
            $existingJournal = JournalEntry::where('reference_type', DeliveryOrder::class)
                ->where('reference_id', $deliveryOrder->id)
                ->first();

            if (!$existingJournal && $totalSales > 0) {
                JournalService::journalDeliveryOrder(
                    $totalSales,
                    $totalHpp,
                    $deliveryOrder->id,
                    $deliveryOrder->created_by ?? auth()->id()
                );

                Log::info('DO Jurnal Created SUCCESS', [
                    'do_id' => $deliveryOrder->id,
                    'total_sales' => $totalSales,
                    'total_hpp' => $totalHpp,
                ]);
            } else {
                Log::warning('DO Jurnal SKIPPED', [
                    'do_id' => $deliveryOrder->id,
                    'existing_journal' => $existingJournal ? 'YES' : 'NO',
                    'total_sales' => $totalSales,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('DO Jurnal Error: ' . $e->getMessage(), [
                'do_id' => $deliveryOrder->id,
                'total_sales' => $totalSales,
                'total_hpp' => $totalHpp,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function reverseDelivery(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder = $deliveryOrder->fresh(['details']);

        foreach ($deliveryOrder->details as $detail) {
            $soDetail = null;
            if ($detail->so_detail_id) {
                $soDetail = SalesOrderDetail::find($detail->so_detail_id);
            } elseif ($deliveryOrder->so_id) {
                $soDetail = SalesOrderDetail::where('so_id', $deliveryOrder->so_id)
                    ->where('product_id', $detail->product_id)
                    ->first();
            }

            if ($soDetail) {
                $soDetail->delivered_qty = max(0, $soDetail->delivered_qty - $detail->qty);
                $soDetail->remaining_qty = $soDetail->qty - $soDetail->delivered_qty;
                $soDetail->saveQuietly();
            }
        }

        $this->updateSalesOrderStatus($deliveryOrder->so_id);
        $this->deleteJournals($deliveryOrder);
    }

    protected function deleteJournals(DeliveryOrder $deliveryOrder): void
    {
        JournalEntry::where('reference_type', DeliveryOrder::class)
            ->where('reference_id', $deliveryOrder->id)
            ->get()
            ->each(function ($journal) {
                $journal->details()->delete();
                $journal->delete();
            });
    }

    protected function updateSalesOrderStatus(?int $soId): void
    {
        if (!$soId) return;

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