<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

use App\Traits\BelongsToTenant;

class GoodsReceiptDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'goods_receipt_details';

    protected $fillable = [
        'gr_id',
        'product_id',
        'qty',
        'buy_price',
        'subtotal',
    ];

    protected $attributes = [
        'qty' => 0,
        'buy_price' => 0,
        'subtotal' => 0,
    ];

    protected $casts = [
        'qty' => 'integer',
        'buy_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $detail) {
            $detail->subtotal = $detail->qty * $detail->buy_price;
        });

        static::created(function (self $detail) {
            self::updateParentTotal($detail->gr_id);
            self::updatePurchaseOrder($detail, $detail->qty);
            self::updateProductLastBuyPrice($detail);
            self::updateStock($detail, $detail->qty);
        });

        static::updated(function (self $detail) {
            $originalQty = $detail->getOriginal('qty') ?? 0;
            $delta = $detail->qty - $originalQty;

            self::updateParentTotal($detail->gr_id);
            self::updatePurchaseOrder($detail, $delta);
            self::updateProductLastBuyPrice($detail);
            self::updateStock($detail, $delta);
        });

        static::deleted(function (self $detail) {
            self::updateParentTotal($detail->gr_id);
            self::updatePurchaseOrder($detail, -$detail->qty);
            self::updateStock($detail, -$detail->qty);
        });
    }

    protected static function updateParentTotal(?int $grId): void
    {
        if (!$grId) return;

        $totals = DB::table('goods_receipt_details')
            ->where('gr_id', $grId)
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(qty * buy_price), 0) as total_amount')
            ->first();

        DB::table('goods_receipts')->where('id', $grId)->update([
            'total_qty' => $totals->total_qty ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
        ]);
    }

    protected static function updatePurchaseOrder(self $detail, int $delta): void
    {
        $gr = $detail->goodsReceipt;
        if (!$gr || !$gr->po_id) {
            return;
        }

        $poDetail = \App\Models\PurchaseOrderDetail::where('po_id', $gr->po_id)
            ->where('product_id', $detail->product_id)
            ->first();

        if (!$poDetail) {
            return;
        }

        $poDetail->received_qty = max(0, ($poDetail->received_qty ?? 0) + $delta);
        $poDetail->remaining_qty = max(0, $poDetail->qty - $poDetail->received_qty);
        $poDetail->save();

        $po = \App\Models\PurchaseOrder::with('details')->find($gr->po_id);
        if ($po) {
            $totalRemaining = $po->details->sum('remaining_qty');
            $totalQty = $po->details->sum('qty');

            if ($totalRemaining == 0 && $totalQty > 0) {
                $po->status = 'COMPLETE';
            } elseif ($totalRemaining < $totalQty) {
                $po->status = 'PARTIAL';
            }
            $po->save();
        }
    }

    protected static function updateProductLastBuyPrice(self $detail): void
    {
        $product = \App\Models\Product::find($detail->product_id);
        if ($product && $detail->buy_price > 0) {
            $product->last_buy_price = $detail->buy_price;
            $product->save();
        }
    }

    public static function updateStock(self $detail, int $delta): void
    {
        $gr = $detail->goodsReceipt;
        if (!$gr || !$gr->warehouse_id) {
            return;
        }

        $stock = \App\Models\ProductStock::firstOrCreate(
            [
                'product_id' => $detail->product_id,
                'warehouse_id' => $gr->warehouse_id,
            ],
            [
                'physical_stock' => 0,
                'outstanding_stock' => 0,
                'available_stock' => 0,
            ]
        );

        $qtyBefore = $stock->physical_stock;
        $stock->physical_stock = max(0, $stock->physical_stock + $delta);
        $stock->available_stock = max(0, $stock->physical_stock - $stock->outstanding_stock);
        $stock->save();

        \App\Models\StockMovement::create([
            'product_id' => $detail->product_id,
            'warehouse_id' => $gr->warehouse_id,
            'qty_before' => $qtyBefore,
            'qty_after' => $stock->physical_stock,
            'delta' => $stock->physical_stock - $qtyBefore,
            'type' => 'GR',
            'reference_type' => self::class,
            'reference_id' => $detail->gr_id,
            'notes' => 'Goods Receipt #' . ($gr->gr_number ?? $gr->id),
        ]);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'gr_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}