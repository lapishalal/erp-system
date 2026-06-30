<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class Product extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'brand_id',
        'category_id',
        'unit',
        'default_sale_price',
        'last_buy_price',
        'min_stock',
        'description',
        'is_active',
        'created_by',
		'sku',
    ];

    protected $casts = [
        'default_sale_price' => 'decimal:2',
        'last_buy_price' => 'decimal:2',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];
    
    /**
    * Get HPP (cost price) with fallback to product_buy_prices.
    * Priority: 1) last_buy_price  2) latest product_buy_prices.buy_price  3) 0
    */
    public function getHpp(): float
    {
        if ($this->last_buy_price > 0) {
            return (float) $this->last_buy_price;
        }

        $latestBuyPrice = $this->buyPrices()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('buy_price');

        return (float) ($latestBuyPrice ?? 0);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class)->where('warehouse_id', 1);
    }

    public function buyPrices(): HasMany
    {
        return $this->hasMany(ProductBuyPrice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}