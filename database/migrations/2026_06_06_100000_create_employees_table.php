<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 50)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('position', 100)->nullable(); // jabatan
            $table->string('department', 100)->nullable(); // divisi
            $table->date('join_date')->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->boolean('is_active')->default(true);

            // Komponen gaji
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('position_allowance', 15, 2)->default(0);
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('other_allowance', 15, 2)->default(0);

            // BPJS (persentase dari gaji pokok)
            $table->decimal('bpjs_kesehatan_company_pct', 5, 2)->default(4.00);
            $table->decimal('bpjs_kesehatan_employee_pct', 5, 2)->default(1.00);
            $table->decimal('bpjs_ketenagakerjaan_company_pct', 5, 2)->default(6.24); // JKK+JKM+JHT+JP
            $table->decimal('bpjs_ketenagakerjaan_employee_pct', 5, 2)->default(3.00); // JHT+JP

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->unsignedTinyInteger('month');
            $table->string('period_name', 50); // Juni 2026
            $table->date('cutoff_date')->nullable(); // tanggal cut-off
            $table->date('payment_date')->nullable(); // tanggal bayar
            $table->enum('status', ['DRAFT', 'PROCESSED', 'PAID'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payroll_period_id');
            $table->string('payroll_number')->unique();

            // Earnings
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_allowances', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0); // pokok + tunjangan

            // BPJS
            $table->decimal('bpjs_kesehatan_company', 15, 2)->default(0);
            $table->decimal('bpjs_kesehatan_employee', 15, 2)->default(0);
            $table->decimal('bpjs_ketenagakerjaan_company', 15, 2)->default(0);
            $table->decimal('bpjs_ketenagakerjaan_employee', 15, 2)->default(0);
            $table->decimal('total_bpjs_company', 15, 2)->default(0);
            $table->decimal('total_bpjs_employee', 15, 2)->default(0);

            // Deductions
            $table->decimal('total_deductions', 15, 2)->default(0); // potongan lain + BPJS karyawan

            // Summary
            $table->decimal('gross_salary', 15, 2)->default(0); // total earnings + BPJS company
            $table->decimal('net_salary', 15, 2)->default(0); // gaji bersih

            $table->enum('status', ['DRAFT', 'PROCESSED', 'PAID'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'payroll_period_id']);
            $table->index('payroll_period_id');
        });

        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->enum('type', ['EARNING', 'DEDUCTION', 'BPJS_COMPANY', 'BPJS_EMPLOYEE']);
            $table->string('name', 100); // Gaji Pokok, Tunjangan Jabatan, BPJS Kesehatan, dll
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('payroll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employees');
    }
};