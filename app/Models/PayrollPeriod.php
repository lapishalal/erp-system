<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'year', 'month', 'period_name', 'cutoff_date', 'payment_date',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'cutoff_date' => 'date',
        'payment_date' => 'date',
        'status' => 'string',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function getTotalNetSalaryAttribute(): float
    {
        return (float) $this->payrolls()->sum('net_salary');
    }
}