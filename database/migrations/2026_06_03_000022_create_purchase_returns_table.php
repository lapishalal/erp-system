<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('reference_gr_id')->nullable();
            $table->date('date');
            $table->enum('status', ['DRAFT', 'PROCESSED', 'CANCEL'])->default('DRAFT');
            $table->integer('total_qty')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('reference_gr_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};