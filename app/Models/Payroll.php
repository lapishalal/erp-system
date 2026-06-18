<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\BelongsToTenant;
class Payroll extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'employee_id', 'payroll_period_id', 'payroll_number',
        'basic_salary', 'total_allowances', 'total_earnings',
        'bpjs_kesehatan_company', 'bpjs_kesehatan_employee',
        'bpjs_ketenagakerjaan_company', 'bpjs_ketenagakerjaan_employee',
        'total_bpjs_company', 'total_bpjs_employee',
        'total_deductions', 'gross_salary', 'net_salary',
        'status', 'notes', 'pph21_deduction', 'loan_deduction', 'alpha_days', 'alpha_deduction', 'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'bpjs_kesehatan_company' => 'decimal:2',
        'bpjs_kesehatan_employee' => 'decimal:2',
        'bpjs_ketenagakerjaan_company' => 'decimal:2',
        'bpjs_ketenagakerjaan_employee' => 'decimal:2',
        'total_bpjs_company' => 'decimal:2',
        'total_bpjs_employee' => 'decimal:2',
		'pph21_deduction' => 'decimal:2',
		'loan_deduction' => 'decimal:2',
		'alpha_days' => 'integer',
		'alpha_deduction' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
		
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}