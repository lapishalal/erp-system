<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class MarketplaceOrderItemMapping extends Model
{
    use BelongsToTenant;

    protected $table = 'marketplace_order_item_mappings';

    protected $fillable = [
        'tenant_id',
        'marketplace_order_item_id',
        'product_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function marketplaceOrderItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrderItem::class, 'marketplace_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
