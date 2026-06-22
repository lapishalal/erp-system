<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_order_details', 'so_detail_id')) {
                $table->foreignId('so_detail_id')->nullable()->after('product_id');
                $table->index('so_detail_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_details', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_order_details', 'so_detail_id')) {
                $table->dropColumn('so_detail_id');
            }
        });
    }
};
