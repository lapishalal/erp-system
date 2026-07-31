<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_order_item_mappings', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('marketplace_order_item_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty')->default(1)->comment('Qty produk dalam bundle');
            $table->timestamps();

            $table->foreign('marketplace_order_item_id', 'moim_oi_fk')
                ->references('id')->on('marketplace_order_items')
                ->cascadeOnDelete();
            $table->foreign('product_id', 'moim_p_fk')
                ->references('id')->on('products')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'marketplace_order_item_id'], 'moim_tenant_item_idx');
            $table->index(['tenant_id', 'product_id'], 'moim_tenant_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_item_mappings');
    }
};
