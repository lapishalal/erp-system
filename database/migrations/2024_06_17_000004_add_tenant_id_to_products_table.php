<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom tenant_id
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });

        // 2. Update data existing ke tenant default (yang dibuat di Part 1.5)
        $defaultTenant = DB::table('tenants')->first();
        if ($defaultTenant) {
            DB::table('products')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant->id]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};