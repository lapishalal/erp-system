<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class SalesInvoice extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'so_id',
        'date',
        'due_date',
        'total',
        'paid_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalesInvoiceDetail::class, 'invoice_id');
    }

    public function cashIns(): HasMany
    {
        return $this->hasMany(CashIn::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}