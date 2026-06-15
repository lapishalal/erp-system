<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class AccountBalance extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'account_id',
        'period_year',
        'period_month',
        'beginning_balance',
        'debit_mutation',
        'credit_mutation',
        'ending_balance',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'beginning_balance' => 'decimal:2',
        'debit_mutation' => 'decimal:2',
        'credit_mutation' => 'decimal:2',
        'ending_balance' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}