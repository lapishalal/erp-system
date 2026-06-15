<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->integer('period_year');
            $table->integer('period_month');
            $table->decimal('beginning_balance', 15, 2)->default(0);
            $table->decimal('debit_mutation', 15, 2)->default(0);
            $table->decimal('credit_mutation', 15, 2)->default(0);
            $table->decimal('ending_balance', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'period_year', 'period_month']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};