<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->boolean('is_dropship')->default(false)->after('source');
            $table->foreignId('dropship_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->after('is_dropship');
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->boolean('is_dropship')->default(false)->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['dropship_supplier_id']);
            $table->dropColumn(['is_dropship', 'dropship_supplier_id']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('is_dropship');
        });
    }
};
