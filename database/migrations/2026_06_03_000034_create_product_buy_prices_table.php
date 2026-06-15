<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_buy_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('gr_id');
            $table->unsignedBigInteger('supplier_id');
            $table->decimal('buy_price', 15, 2);
            $table->integer('qty');
            $table->date('date');
            $table->timestamps();

            $table->index('product_id');
            $table->index('gr_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_buy_prices');
    }
};