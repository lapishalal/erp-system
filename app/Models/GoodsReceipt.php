<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\BelongsToTenant;
class GoodsReceipt extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'gr_number',
        'po_id',
        'supplier_id',
        'warehouse_id',
        'date',
        'status',
        'total_qty',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_qty' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(GoodsReceiptDetail::class, 'gr_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}