<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('loan_date');
            $table->decimal('amount', 15, 2);
            $table->integer('installment_count')->default(1);
            $table->decimal('installment_amount', 15, 2);
            $table->integer('paid_count')->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['ACTIVE', 'PAID'])->default('ACTIVE');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('pph21_deduction', 15, 2)->default(0)->after('total_bpjs_employee');
            $table->decimal('loan_deduction', 15, 2)->default(0)->after('pph21_deduction');
            $table->integer('alpha_days')->default(0)->after('loan_deduction');
            $table->decimal('alpha_deduction', 15, 2)->default(0)->after('alpha_days');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('pph21_enabled')->default(false)->after('other_allowance');
            $table->string('ptkp_status', 10)->default('TK/0')->after('pph21_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['pph21_deduction', 'loan_deduction', 'alpha_days', 'alpha_deduction']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['pph21_enabled', 'ptkp_status']);
        });
    }
};