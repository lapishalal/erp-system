<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class SalesOrderDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sales_order_details';

    protected $fillable = [
        'tenant_id',
        'so_id',
        'product_id',
        'qty',
        'unit_price',
        'cost_price',
        'delivered_qty',
        'remaining_qty',
        'subtotal',
        'profit',
    ];

    protected $attributes = [
        'delivered_qty' => 0,
        'remaining_qty' => 0,
        'cost_price' => 0,
        'profit' => 0,
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'delivered_qty' => 'integer',
        'remaining_qty' => 'integer',
        'subtotal' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // ✅ FIX #1: Update outstanding_stock saat SO detail dibuat
        static::created(function (self $detail) {
            self::updateOutstandingStock($detail, $detail->qty);
            // Set remaining_qty saat create
            $detail->remaining_qty = $detail->qty;
            $detail->saveQuietly();
        });

        // ✅ FIX #1: Adjust outstanding_stock saat SO detail diubah (qty berubah)
        static::updated(function (self $detail) {
            if ($detail->isDirty('qty')) {
                $originalQty = $detail->getOriginal('qty') ?? 0;
                $delta = $detail->qty - $originalQty;
                self::updateOutstandingStock($detail, $delta);

                // Adjust remaining_qty juga
                $detail->remaining_qty = max(0, $detail->qty - ($detail->delivered_qty ?? 0));
                $detail->saveQuietly();
            }
        });

        // ✅ FIX #1: Kembalikan outstanding_stock saat SO detail dihapus
        static::deleted(function (self $detail) {
            self::updateOutstandingStock($detail, -$detail->qty);
        });
    }

    /**
     * Update ProductStock.outstanding_stock
     * delta positif = pesanan bertambah, delta negatif = pesanan berkurang
     */
    protected static function updateOutstandingStock(self $detail, int $delta): void
    {
        $so = $detail->salesOrder;
        if (!$so) {
            return;
        }

        // Cari warehouse default (gudang 1) atau dari DO terkait
        // Simplifikasi: outstanding_stock di semua gudang? Atau gudang default?
        // Logika bisnis: outstanding_stock adalah komitmen penjualan, tidak terikat gudang spesifik.
        // Tapi di tabel product_stocks ada warehouse_id. Kita update gudang default (id=1) saja.
        $warehouseId = 1;

        $stock = \App\Models\ProductStock::firstOrCreate(
            [
                'product_id' => $detail->product_id,
                'warehouse_id' => $warehouseId,
            ],
            [
                'physical_stock' => 0,
                'outstanding_stock' => 0,
                'available_stock' => 0,
            ]
        );

        $stock->outstanding_stock = max(0, $stock->outstanding_stock + $delta);
        $stock->available_stock = $stock->physical_stock - $stock->outstanding_stock;
        $stock->save();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getPendingQtyAttribute(): int
    {
        return max(0, (int) $this->remaining_qty);
    }

    public function getFormattedPendingQtyAttribute(): string
    {
        return number_format($this->pending_qty, 0, ',', '.');
    }

    public function scopePending($query)
    {
        return $query->where('remaining_qty', '>', 0);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithActiveSalesOrder($query)
    {
        return $query->whereHas('salesOrder', function ($q) {
            $q->whereIn('status', ['OPEN', 'PARTIAL'])
              ->whereNotIn('status', ['COMPLETE', 'CANCEL', 'DRAFT']);
        });
    }
}