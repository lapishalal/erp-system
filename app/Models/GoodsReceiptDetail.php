<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class GoodsReceiptDetail extends Model
{
    use HasFactory;

    protected $table = 'goods_receipt_details';

    protected $fillable = [
        'gr_id',
        'product_id',
        'qty',
        'buy_price',
        'subtotal',
    ];

    protected $attributes = [
        'qty' => 0,
        'buy_price' => 0,
        'subtotal' => 0,
    ];

    protected $casts = [
        'qty' => 'integer',
        'buy_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Auto hitung subtotal saat save
        static::saving(function (self $detail) {
            $detail->subtotal = $detail->qty * $detail->buy_price;
        });

        // ✅ Update parent GR total setelah detail tersimpan
        static::saved(function (self $detail) {
            self::updateParentTotal($detail->gr_id);
        });

        // ✅ Update parent GR total setelah detail dihapus
        static::deleted(function (self $detail) {
            self::updateParentTotal($detail->gr_id);
        });
    }

    protected static function updateParentTotal(?int $grId): void
    {
        if (!$grId) return;

        $totals = DB::table('goods_receipt_details')
            ->where('gr_id', $grId)
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(qty * buy_price), 0) as total_amount')
            ->first();

        DB::table('goods_receipts')->where('id', $grId)->update([
            'total_qty' => $totals->total_qty ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
        ]);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'gr_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}