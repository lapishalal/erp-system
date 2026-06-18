// database/migrations/2024_06_17_000003_add_tenant_id_to_permission_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Permissions
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'name', 'guard_name']);
        });

        // 2. Roles
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'name', 'guard_name']);
        });

        // 3. Model has permissions (user/employee punya permission)
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('permission_id')->constrained('tenants')->onDelete('cascade');
        });

        // 4. Model has roles
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('role_id')->constrained('tenants')->onDelete('cascade');
        });

        // 5. Role has permissions
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('permission_id')->constrained('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $tables = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
        
        foreach ($tables as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};