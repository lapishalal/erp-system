<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('do_number')->unique();
            $table->unsignedBigInteger('so_id');
            $table->date('date');
            $table->unsignedBigInteger('customer_id');
            $table->enum('status', ['DRAFT', 'SHIPPED', 'DELIVERED', 'CANCEL'])->default('DRAFT');
            $table->integer('total_qty')->default(0);
            $table->text('notes')->nullable();
            $table->string('driver')->nullable();
            $table->string('vehicle')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('so_id');
            $table->index('customer_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};