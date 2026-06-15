<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number')->unique();
            $table->unsignedBigInteger('po_id')->nullable();
            $table->date('date');
            $table->unsignedBigInteger('supplier_id');
            $table->enum('status', ['DRAFT', 'RECEIVED', 'CANCEL'])->default('DRAFT');
            $table->integer('total_qty')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('po_id');
            $table->index('supplier_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};