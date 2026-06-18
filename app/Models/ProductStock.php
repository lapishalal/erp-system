<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
    
    // =========================================================
    // ACCESSORS (untuk kolom Pending ke Customer di tabel)
    // =========================================================

    public function getTotalPendingCustomerAttribute(): int
    {
        return (int) DB::table('sales_order_details')
            ->where('sales_order_details.product_id', $this->product_id)
            ->where('sales_order_details.remaining_qty', '>', 0)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sales_orders')
                    ->whereColumn('sales_orders.id', 'sales_order_details.so_id')
                    ->whereIn('sales_orders.status', ['OPEN', 'PARTIAL']);
            })
            ->sum('sales_order_details.remaining_qty');
    }

    public function getFormattedTotalPendingAttribute(): string
    {
        return number_format($this->total_pending_customer, 0, ',', '.');
    }
    
}