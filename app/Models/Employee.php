<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nik', 'name', 'email', 'phone', 'npwp', 'position', 'department',
        'join_date', 'bank_name', 'bank_account', 'bank_account_name', 'is_active',
        'basic_salary', 'position_allowance', 'meal_allowance', 'transport_allowance', 'other_allowance',
        'bpjs_kesehatan_company_pct', 'bpjs_kesehatan_employee_pct',
        'bpjs_ketenagakerjaan_company_pct', 'bpjs_ketenagakerjaan_employee_pct', 'pph21_enabled', 'ptkp_status',
        'created_by', 
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'join_date' => 'date',
        'basic_salary' => 'decimal:2',
        'position_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'bpjs_kesehatan_company_pct' => 'decimal:2',
        'bpjs_kesehatan_employee_pct' => 'decimal:2',
        'bpjs_ketenagakerjaan_company_pct' => 'decimal:2',
        'bpjs_ketenagakerjaan_employee_pct' => 'decimal:2',
		'pph21_enabled' => 'boolean',
		'ptkp_status' => 'string',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function getTotalAllowancesAttribute(): float
    {
        return (float) ($this->position_allowance + $this->meal_allowance + $this->transport_allowance + $this->other_allowance);
    }

    public function getTotalEarningsAttribute(): float
    {
        return (float) ($this->basic_salary + $this->getTotalAllowancesAttribute());
    }
	
	public function loans(): HasMany
	{
		return $this->hasMany(EmployeeLoan::class);
	}
	
	public function activeLoan(): ?EmployeeLoan
	{
		return $this->loans()->where('status', 'ACTIVE')->first();
	}
}