<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class StockTransaction extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'reference_type',
        'reference_id',
        'qty',
        'price',
        'remaining_stock',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'remaining_stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}