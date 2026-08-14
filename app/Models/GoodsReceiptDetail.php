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
            self::createProductBuyPrice($detail);
            self::syncParentJournal($detail->gr_id);
        });

        static::updated(function (self $detail) {
            $originalQty = (int) ($detail->getOriginal('qty') ?? 0);
            $originalProductId = $detail->getOriginal('product_id');

            if ($originalProductId && $originalProductId !== $detail->product_id) {
                // ============================================
                // FIX: Produk diganti (misal salah pilih barang A, harusnya B)
                // 1) Balik efek di produk lama
                // 2) Terapkan ke produk baru
                // ============================================
                self::updatePurchaseOrder($detail, -$originalQty, $originalProductId);
                self::updateStock($detail, -$originalQty, $originalProductId);
                self::restoreLastBuyPrice($originalProductId, $detail->gr_id);
                \App\Models\ProductBuyPrice::where('gr_id', $detail->gr_id)
                    ->where('product_id', $originalProductId)
                    ->delete();

                self::updatePurchaseOrder($detail, $detail->qty);
                self::updateProductLastBuyPrice($detail);
                self::updateStock($detail, $detail->qty);
                self::createProductBuyPrice($detail);
            } else {
                // Qty / harga berubah saja
                $delta = $detail->qty - $originalQty;
                self::updatePurchaseOrder($detail, $delta);
                self::updateProductLastBuyPrice($detail);
                self::updateStock($detail, $delta);
                self::updateProductBuyPrice($detail);
            }

            self::updateParentTotal($detail->gr_id);
            self::syncParentJournal($detail->gr_id);
        });

        static::deleted(function (self $detail) {
            self::updateParentTotal($detail->gr_id);
            self::updatePurchaseOrder($detail, -$detail->qty);
            self::updateStock($detail, -$detail->qty);
            self::restoreLastBuyPrice($detail->product_id, $detail->gr_id);
            \App\Models\ProductBuyPrice::where('gr_id', $detail->gr_id)
                ->where('product_id', $detail->product_id)
                ->delete();
            self::syncParentJournal($detail->gr_id);
        });
    }

    // ============================================
    // Sinkron jurnal GR (mask dari status RECEIVED)
    // Formula createJournal dipanggil dari GoodsReceipt::createJournal — idempotent
    // ============================================
    protected static function syncParentJournal(int $grId): void
    {
        $gr = \App\Models\GoodsReceipt::find($grId);
        if ($gr) {
            \App\Models\GoodsReceipt::createJournal($gr);
        }
    }

    protected static function createProductBuyPrice(self $detail): void
    {
        $gr = \App\Models\GoodsReceipt::find($detail->gr_id);
        if (!$gr) {
            return;
        }

        \App\Models\ProductBuyPrice::create([
            'product_id' => $detail->product_id,
            'gr_id' => $gr->id,
            'supplier_id' => $gr->supplier_id,
            'buy_price' => $detail->buy_price,
            'qty' => $detail->qty,
            'date' => $gr->date,
        ]);
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

    protected static function updatePurchaseOrder(self $detail, int $delta, ?int $productId = null): void
    {
        $gr = $detail->goodsReceipt;
        if (!$gr || !$gr->po_id) {
            return;
        }

        $targetProductId = $productId ?? $detail->product_id;

        $poDetail = \App\Models\PurchaseOrderDetail::where('po_id', $gr->po_id)
            ->where('product_id', $targetProductId)
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
            $totalReceived = $po->details->sum('received_qty');

            if ($totalReceived == 0) {
                $po->status = 'DRAFT';
            } elseif ($totalRemaining == 0 && $totalQty > 0) {
                $po->status = 'COMPLETE';
            } elseif ($totalRemaining < $totalQty) {
                $po->status = 'PARTIAL';
            }
            $po->save();
        }
    }

    protected static function updateProductLastBuyPrice(self $detail, ?int $productId = null): void
    {
        $targetProductId = $productId ?? $detail->product_id;
        $product = \App\Models\Product::find($targetProductId);
        if ($product && $detail->buy_price > 0) {
            $product->last_buy_price = $detail->buy_price;
            $product->save();
        }
    }

    protected static function restoreLastBuyPrice(int $productId, ?int $excludeGrId): void
    {
        $latest = \App\Models\ProductBuyPrice::where('product_id', $productId)
            ->where('gr_id', '!=', $excludeGrId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $product = \App\Models\Product::find($productId);
        if ($product) {
            $product->last_buy_price = $latest?->buy_price ?? 0;
            $product->save();
        }
    }

    protected static function updateProductBuyPrice(self $detail): void
    {
        $gr = \App\Models\GoodsReceipt::find($detail->gr_id);
        if (!$gr) {
            return;
        }

        \App\Models\ProductBuyPrice::where('gr_id', $detail->gr_id)
            ->where('product_id', $detail->product_id)
            ->update([
                'buy_price' => $detail->buy_price,
                'qty' => $detail->qty,
                'date' => $gr->date,
            ]);
    }

    public static function updateStock(self $detail, int $delta, ?int $productId = null): void
    {
        $gr = $detail->goodsReceipt;
        if (!$gr || !$gr->warehouse_id) {
            return;
        }

        $targetProductId = $productId ?? $detail->product_id;

        $stock = \App\Models\ProductStock::firstOrCreate(
            [
                'product_id' => $targetProductId,
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
        $stock->available_stock = $stock->physical_stock;
        $stock->save();

        $appliedDelta = $stock->physical_stock - $qtyBefore;

        \App\Models\StockMovement::create([
            'product_id' => $targetProductId,
            'warehouse_id' => $gr->warehouse_id,
            'qty_before' => $qtyBefore,
            'qty_after' => $stock->physical_stock,
            'delta' => $appliedDelta,
            'type' => 'GR',
            'reference_type' => self::class,
            'reference_id' => $detail->gr_id,
            'notes' => 'Goods Receipt #' . ($gr->gr_number ?? $gr->id),
        ]);

        $supplierName = $gr->supplier?->name ?? '-';

        \App\Models\StockTransaction::create([
            'product_id' => $targetProductId,
            'warehouse_id' => $gr->warehouse_id,
            'type' => $appliedDelta >= 0 ? 'IN' : 'OUT',
            'reference_type' => self::class,
            'reference_id' => $detail->gr_id,
            'qty' => $appliedDelta,
            'price' => $detail->buy_price,
            'remaining_stock' => $stock->physical_stock,
            'notes' => 'GR #' . ($gr->gr_number ?? $gr->id) . ' | Supplier: ' . $supplierName,
            'created_by' => auth()->id(),
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