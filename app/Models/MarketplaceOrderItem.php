<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use App\Traits\BelongsToTenant;

class MarketplaceOrderItem extends Model
{
    use BelongsToTenant;

    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'tenant_id',
        'marketplace_order_id',
        'platform_sku_id',
        'seller_sku',
        'product_name',
        'variation',
        'quantity',
        'unit_price',
        'subtotal_after_discount',
        'mapped_product_id',
        'is_mapped',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal_after_discount' => 'decimal:2',
        'is_mapped' => 'boolean',
    ];

    public function marketplaceOrder(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function mappedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mapped_product_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItemMapping::class, 'marketplace_order_item_id');
    }

    /**
     * Daftar komponen produk ERP untuk item ini.
     * - Bundle: dari tabel marketplace_order_item_mappings (produk + qty per komponen)
     * - Single: fallback ke mapped_product_id (qty 1)
     */
    public function mappedComponents(): Collection
    {
        $this->loadMissing('mappings.product');

        if ($this->mappings->isNotEmpty()) {
            return $this->mappings->map(fn ($m) => [
                'product' => $m->product,
                'qty' => $m->qty,
            ])->filter(fn ($c) => $c['product'] !== null)->values();
        }

        if ($this->mappedProduct) {
            return collect([[
                'product' => $this->mappedProduct,
                'qty' => 1,
            ]]);
        }

        return collect();
    }

    public function isBundle(): bool
    {
        $this->loadMissing('mappings');

        return $this->mappings->count() > 1;
    }

    /**
     * Scope: only unmapped items
     */
    public function scopeUnmapped($query)
    {
        return $query->where('is_mapped', false);
    }

    /**
     * Scope: only mapped items
     */
    public function scopeMapped($query)
    {
        return $query->where('is_mapped', true);
    }

    /**
     * Get display name for the item (product name + variation)
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->product_name ?? 'Unknown Product';
        if ($this->variation) {
            $name .= ' - ' . $this->variation;
        }
        return $name;
    }
}