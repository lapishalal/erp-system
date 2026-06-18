<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class PurchaseReturn extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'return_number',
        'supplier_id',
        'reference_gr_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function referenceGr(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'reference_gr_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}