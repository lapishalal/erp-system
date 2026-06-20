<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;

class StockOpnameDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'stock_opname_details';

    protected $fillable = [
        'opname_id',
        'product_id',
        'system_qty',
        'physical_qty',
        'difference_qty',
        'notes',
    ];

    protected $attributes = [
        'system_qty' => 0,
        'physical_qty' => 0,
        'difference_qty' => 0,
    ];

    protected $casts = [
        'system_qty' => 'integer',
        'physical_qty' => 'integer',
        'difference_qty' => 'integer',
    ];

    protected static function booted(): void
    {
        // ✅ Pengaman kedua: setiap detail opname tersimpan,
        //    jika parent opname status COMPLETED, langsung sync stok.
        static::saved(function (self $detail) {
            $opname = $detail->opname;
            if ($opname && $opname->status === 'COMPLETED') {
                StockOpname::syncStock($detail, $opname->warehouse_id);
            }
        });

        // ✅ Saat detail dihapus (misal edit opname hapus baris),
        //    jika parent opname status COMPLETED, kembalikan stok ke system_qty.
        static::deleting(function (self $detail) {
            $opname = $detail->opname;
            if ($opname && $opname->status === 'COMPLETED') {
                StockOpname::rollbackStock($detail, $opname->warehouse_id);
            }
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}