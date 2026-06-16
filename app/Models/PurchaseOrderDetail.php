<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_id',
        'product_id',
        'qty',
        'unit_price',
        'received_qty',
        'remaining_qty',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'received_qty' => 'integer',
        'remaining_qty' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $detail) {
            if (empty($detail->remaining_qty) && $detail->qty > 0) {
                $detail->remaining_qty = $detail->qty;
            }
            if (empty($detail->received_qty)) {
                $detail->received_qty = 0;
            }
            if (empty($detail->subtotal)) {
                $detail->subtotal = $detail->qty * $detail->unit_price;
            }
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}