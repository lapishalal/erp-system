<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class CashIn extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $table = 'cash_in';

    protected $fillable = [
        'account_id',
        'date',
        'type',
        'reference_type',
        'reference_id',
        'customer_id',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}