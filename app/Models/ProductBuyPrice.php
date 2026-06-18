<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;
class ProductBuyPrice extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'product_buy_prices';

    protected $fillable = [
        'product_id',
        'gr_id',
        'supplier_id',
        'buy_price',
        'qty',
        'date',
    ];

    protected $casts = [
        'buy_price' => 'decimal:2',
        'qty' => 'integer',
        'date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'gr_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}