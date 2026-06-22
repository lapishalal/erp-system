<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->foreignId('connection_id')->nullable()->change();
            $table->foreign('connection_id')->references('id')->on('marketplace_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->foreignId('connection_id')->nullable(false)->change();
            $table->foreign('connection_id')->references('id')->on('marketplace_connections')->cascadeOnDelete();
        });
    }
};