<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class Customer extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'pic',
        'credit_limit',
        'outstanding_amount',
        'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function cashIns(): HasMany
    {
        return $this->hasMany(CashIn::class);
    }
}