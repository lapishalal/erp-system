<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;
class SalesInvoiceDetail extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sales_invoice_details';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'qty',
        'price',
        'subtotal',
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

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}