<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderDetail extends Model
{
    use HasFactory;

    protected $table = 'delivery_order_details';

    protected $fillable = [
        'do_id',
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

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'do_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}