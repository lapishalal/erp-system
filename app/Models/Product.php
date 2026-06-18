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
    ];

    protected $casts = [
        'default_sale_price' => 'decimal:2',
        'last_buy_price' => 'decimal:2',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];

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