<?php

namespace App\Models;

use App\Enums\MarketplacePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class MarketplaceOrder extends Model
{
    
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'connection_id',
        'platform',
        'platform_order_id',
        'platform_order_sn',
        'sales_order_id',
        'status',
        'synced_at',
        'processed_at',
        'raw_payload',
        'mapped_items',
        'is_mapped',
        'error_message',
        'is_hidden',
        'settlement_amount',
        'needs_review',
        'review_status',
        'reviewed_at',
        'reviewed_by',
        'review_note',
    ];

    protected $casts = [
        'platform' => MarketplacePlatform::class,
        'raw_payload' => 'array',
        'mapped_items' => 'array',
        'synced_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_mapped' => 'boolean',
        'is_hidden' => 'boolean',
        'settlement_amount' => 'decimal:2',
        'needs_review' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function getMappedItemsCountAttribute(): int
    {
        $this->loadMissing('items');

        return $this->items->where('is_mapped', true)->count();
    }

    public function getHasUnmappedItemsAttribute(): bool
    {
        $this->loadMissing('items');

        return $this->items->where('is_mapped', false)->isNotEmpty();
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'connection_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'marketplace_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}