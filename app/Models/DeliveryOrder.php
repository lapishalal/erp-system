<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class DeliveryOrder extends Model  // ← HARUS DeliveryOrder
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'do_number',
        'so_id',
        'date',
        'customer_id',
        'warehouse_id',
        'status',
        'total_qty',
        'notes',
        'driver',
        'vehicle',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_qty' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $do) {
            if ($do->isDirty('status')) {
                $oldStatus = $do->getOriginal('status');
                $newStatus = $do->status;

                $wasShipped = in_array($oldStatus, ['SHIPPED', 'DELIVERED']);
                $isShipped = in_array($newStatus, ['SHIPPED', 'DELIVERED']);

                if (!$wasShipped && $isShipped) {
                    $do->load('details');
                    foreach ($do->details as $detail) {
                        DeliveryOrderDetail::updateStock($detail, $detail->qty);
                        DeliveryOrderDetail::updateOutstandingStock($detail, -$detail->qty);
                    }
                } elseif ($wasShipped && !$isShipped) {
                    $do->load('details');
                    foreach ($do->details as $detail) {
                        DeliveryOrderDetail::updateStock($detail, -$detail->qty);
                        DeliveryOrderDetail::updateOutstandingStock($detail, $detail->qty);
                    }
                }
            }
        });

        static::deleting(function (self $do) {
            if (in_array($do->status, ['SHIPPED', 'DELIVERED'])) {
                $do->load('details');
                foreach ($do->details as $detail) {
                    DeliveryOrderDetail::updateStock($detail, -$detail->qty);
                    DeliveryOrderDetail::updateOutstandingStock($detail, $detail->qty);
                }
            }
        });
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(DeliveryOrderDetail::class, 'do_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}