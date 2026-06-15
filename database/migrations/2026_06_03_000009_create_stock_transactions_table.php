<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('type', ['IN', 'OUT', 'ADJUSTMENT', 'OPNAME']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('qty');
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('remaining_stock');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('product_id');
            $table->index('warehouse_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};