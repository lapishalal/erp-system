// database/migrations/2024_06_17_000002_add_tenant_id_to_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kalau users.id masih bigint, jangan ubah dulu (nanti di Part 2 kita bahas UUID)
            // Sekarang cuma tambah tenant_id
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            
            // Tambah role field simpel (alternatif dari Spatie, atau bisa pakai Spatie nanti)
            // Kalau sudah pakai Spatie, skip ini
            // $table->string('role')->default('Admin')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};