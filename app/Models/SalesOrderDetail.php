<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_order_details';

    protected $fillable = [
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
        return $this->belongsTo(Product::class);
    }
}