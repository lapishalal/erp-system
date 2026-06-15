<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_out', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('date');
            $table->enum('type', ['OPERATIONAL', 'SALARY', 'TRANSPORT', 'MARKETING', 'UTILITIES', 'RENT', 'TAX', 'OTHER']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('category_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_out');
    }
};