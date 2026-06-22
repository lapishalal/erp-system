<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->foreignId('connection_id')->constrained('marketplace_connections')->cascadeOnDelete();
            $table->string('platform');
            $table->string('platform_order_id')->comment('ID order asli dari marketplace');
            $table->string('platform_order_sn')->nullable()->comment('Nomor order marketplace');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('status')->default('pending')->comment('UNPAID, PAID, SHIPPED, COMPLETED, CANCELLED');
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('mapped_items')->nullable();
            $table->boolean('is_mapped')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'platform', 'platform_order_id']);
            $table->unique(['tenant_id', 'platform', 'platform_order_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'sales_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};