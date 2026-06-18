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

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    
    // =========================================================
    // ACCESSORS
    // =========================================================

    /**
     * Pending qty = remaining_qty (jika database sudah maintain dengan benar)
     * Fallback: hitung manual kalau remaining_qty tidak sync
     */
    public function getPendingQtyAttribute(): int
    {
        return max(0, (int) $this->remaining_qty);
    }

    public function getFormattedPendingQtyAttribute(): string
    {
        return number_format($this->pending_qty, 0, ',', '.');
    }

    // =========================================================
    // SCOPES
    // =========================================================

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