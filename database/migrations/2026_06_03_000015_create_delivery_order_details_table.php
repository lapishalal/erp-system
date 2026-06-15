<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('do_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('do_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_details');
    }
};