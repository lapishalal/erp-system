<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class SalesOrder extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'so_number',
        'date',
        'customer_id',
        'status',
        'total_qty',
        'total_amount',
        'total_cost',
        'profit',
        'notes',
        'created_by',
        'approved_by',
        'source',
    ];

    protected $casts = [
        'date' => 'date',
        'total_qty' => 'integer',
        'total_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    // =========================================================
    // FIX: Hanya handle CANCEL — outstanding dihandle oleh SalesOrderDetail::created
    // =========================================================
    protected static function booted(): void
    {
        static::updated(function (self $so) {
            if ($so->isDirty('status') && $so->status === 'CANCEL') {
                $so->load('details');
                foreach ($so->details as $detail) {
                    SalesOrderDetail::updateOutstandingStock($detail, -$detail->qty);
                }
            }
        });
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'so_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalesOrderDetail::class, 'so_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'so_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'so_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}