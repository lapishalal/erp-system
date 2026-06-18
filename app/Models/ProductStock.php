<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class ProductStock extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'physical_stock',
        'outstanding_stock',
        'available_stock',
        'minimum_stock',
        'reorder_point',
        'location',
    ];

    protected $casts = [
        'physical_stock' => 'integer',
        'outstanding_stock' => 'integer',
        'available_stock' => 'integer',
        'minimum_stock' => 'decimal:2',
        'reorder_point' => 'decimal:2',
    ];
    
    protected $appends = [
        'total_pending_customer',
        'formatted_total_pending',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    
}