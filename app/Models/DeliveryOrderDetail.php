<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;

class DeliveryOrderDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'delivery_order_details';

    protected $fillable = [
        'tenant_id',
        'do_id',
        'so_detail_id',
        'product_id',
        'qty',
        'notes',
    ];

    protected $attributes = [
        'qty' => 0,
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (self $detail) {
            self::updateParentTotal($detail->do_id);
            self::updateSalesOrderDetail($detail, $detail->qty);

            $do = $detail->deliveryOrder;
            if ($do && in_array($do->status, ['SHIPPED', 'DELIVERED'])) {
                self::updateStock($detail, $detail->qty);
                self::updateOutstandingStock($detail, -$detail->qty);
            }
        });

        static::updated(function (self $detail) {
            $originalQty = $detail->getOriginal('qty') ?? 0;
            $delta = $detail->qty - $originalQty;

            self::updateParentTotal($detail->do_id);
            self::updateSalesOrderDetail($detail, $delta);

            $do = $detail->deliveryOrder;
            if ($do && in_array($do->status, ['SHIPPED', 'DELIVERED'])) {
                self::updateStock($detail, $delta);
                self::updateOutstandingStock($detail, -$delta);
            }
        });

        // =========================================================
        // FIX: Saat detail dihapus, restore semua stok (apapun status DO)
        // =========================================================
        static::deleted(function (self $detail) {
            self::updateParentTotal($detail->do_id);

            // Kembalikan remaining_qty ke Sales Order
            self::updateSalesOrderDetail($detail, -$detail->qty);

            // Kembalikan stok fisik ke gudang (apapun status)
            self::updateStock($detail, -$detail->qty);

            // Kembalikan outstanding_stock
            self::updateOutstandingStock($detail, $detail->qty);
        });
    }

    protected static function updateParentTotal(?int $doId): void
    {
        if (!$doId) return;

        $totalQty = DB::table('delivery_order_details')
            ->where('do_id', $doId)
            ->sum('qty');

        DB::table('delivery_orders')->where('id', $doId)->update([
            'total_qty' => $totalQty ?? 0,
        ]);
    }

    protected static function updateSalesOrderDetail(self $detail, int $delta): void
    {
        if ($detail->so_detail_id) {
            $soDetail = \App\Models\SalesOrderDetail::find($detail->so_detail_id);
            if ($soDetail) {
                $soDetail->delivered_qty = max(0, ($soDetail->delivered_qty ?? 0) + $delta);
                $soDetail->remaining_qty = max(0, $soDetail->qty - $soDetail->delivered_qty);
                $soDetail->saveQuietly();

                self::updateSalesOrderStatus($soDetail->salesOrder);
                return;
            }
        }

        $do = $detail->deliveryOrder;
        if (!$do || !$do->so_id) {
            return;
        }

        $soDetail = \App\Models\SalesOrderDetail::where('so_id', $do->so_id)
            ->where('product_id', $detail->product_id)
            ->first();

        if (!$soDetail) {
            return;
        }

        $soDetail->delivered_qty = max(0, ($soDetail->delivered_qty ?? 0) + $delta);
        $soDetail->remaining_qty = max(0, $soDetail->qty - $soDetail->delivered_qty);
        $soDetail->saveQuietly();

        self::updateSalesOrderStatus($soDetail->salesOrder);
    }

    protected static function updateSalesOrderStatus(?\App\Models\SalesOrder $salesOrder): void
    {
        if (!$salesOrder) return;

        $salesOrder->load('details');
        $totalQty = (int) $salesOrder->details->sum('qty');
        $totalRemaining = (int) $salesOrder->details->sum('remaining_qty');
        $totalDelivered = (int) $salesOrder->details->sum('delivered_qty');

        if ($totalQty <= 0) return;

        if ($totalRemaining <= 0 && $totalDelivered >= $totalQty) {
            $newStatus = 'COMPLETE';
        } elseif ($totalDelivered > 0) {
            $newStatus = 'PARTIAL';
        } else {
            $newStatus = 'OPEN';
        }

        if ($salesOrder->status !== $newStatus) {
            $salesOrder->updateQuietly(['status' => $newStatus]);
        }
    }

    protected static function updateOutstandingStock(self $detail, int $delta): void
    {
        $warehouseId = $detail->deliveryOrder?->warehouse_id ?? 1;

        $stock = \App\Models\ProductStock::where('product_id', $detail->product_id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$stock) {
            return;
        }

        $stock->outstanding_stock = max(0, $stock->outstanding_stock + $delta);
        $stock->available_stock = $stock->physical_stock - $stock->outstanding_stock;
        $stock->save();
    }

    public static function updateStock(self $detail, int $delta): void
    {
        $do = $detail->deliveryOrder;
        if (!$do || !$do->warehouse_id) {
            return;
        }

        $stock = \App\Models\ProductStock::firstOrCreate(
            [
                'product_id' => $detail->product_id,
                'warehouse_id' => $do->warehouse_id,
            ],
            [
                'physical_stock' => 0,
                'outstanding_stock' => 0,
                'available_stock' => 0,
            ]
        );

        $qtyBefore = $stock->physical_stock;
        $stock->physical_stock = max(0, $stock->physical_stock - $delta);
        $stock->available_stock = $stock->physical_stock - $stock->outstanding_stock;
        $stock->save();

        \App\Models\StockMovement::create([
            'product_id' => $detail->product_id,
            'warehouse_id' => $do->warehouse_id,
            'qty_before' => $qtyBefore,
            'qty_after' => $stock->physical_stock,
            'delta' => $stock->physical_stock - $qtyBefore,
            'type' => 'DO',
            'reference_type' => self::class,
            'reference_id' => $detail->do_id,
            'notes' => 'Delivery Order #' . ($do->do_number ?? $do->id),
        ]);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'do_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrderDetail(): BelongsTo
    {
        return $this->belongsTo(SalesOrderDetail::class, 'so_detail_id');
    }
}
