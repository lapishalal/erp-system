<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->string('platform_sku_id')->nullable()->comment('SKU ID asli dari TikTok');
            $table->string('seller_sku')->nullable()->comment('Seller SKU dari TikTok');
            $table->string('product_name')->nullable()->comment('Nama produk di TikTok');
            $table->string('variation')->nullable()->comment('Variasi produk');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal_after_discount', 15, 2)->default(0);
            $table->foreignId('mapped_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_mapped')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'marketplace_order_id']);
            $table->index(['tenant_id', 'is_mapped']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_items');
    }
};