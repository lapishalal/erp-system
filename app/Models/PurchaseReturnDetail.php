<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

use App\Traits\BelongsToTenant;

class PurchaseReturnDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'purchase_return_details';

    protected $fillable = [
        'return_id',
        'product_id',
        'qty',
        'price',
        'subtotal',
        'notes',
    ];

    protected $attributes = [
        'qty' => 0,
        'price' => 0,
        'subtotal' => 0,
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // ============================================
    // BARU: booted() event untuk auto-hitung & update parent
    // ============================================
    protected static function booted(): void
    {
        // Auto hitung subtotal sebelum save
        static::saving(function (self $detail) {
            $detail->subtotal = $detail->qty * $detail->price;
        });

        // Update parent total saat detail dibuat
        static::created(function (self $detail) {
            self::updateParentTotal($detail->return_id);
        });

        // Update parent total saat detail diubah
        static::updated(function (self $detail) {
            self::updateParentTotal($detail->return_id);
        });

        // Update parent total saat detail dihapus
        static::deleted(function (self $detail) {
            self::updateParentTotal($detail->return_id);
        });
    }

    // ============================================
    // BARU: Update parent PurchaseReturn total
    // ============================================
    protected static function updateParentTotal(?int $returnId): void
    {
        if (!$returnId) return;

        $totals = DB::table('purchase_return_details')
            ->where('return_id', $returnId)
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(qty * price), 0) as total_amount')
            ->first();

        DB::table('purchase_returns')->where('id', $returnId)->update([
            'total_qty' => $totals->total_qty ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
        ]);
    }

    // ============================================
    // TETAP: Relasi yang sudah ada
    // ============================================
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}