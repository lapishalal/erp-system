<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Permissions — drop semua unique index di name+guard_name, lalu buat composite
        $this->dropUniqueIfExists('permissions', 'name', 'guard_name');
        Schema::table('permissions', function ($table) {
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        // 2. Roles — sama
        $this->dropUniqueIfExists('roles', 'name', 'guard_name');
        Schema::table('roles', function ($table) {
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function ($table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('roles', function ($table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
            $table->unique(['name', 'guard_name']);
        });
    }

    protected function dropUniqueIfExists(string $table, string $col1, string $col2): void
    {
        // Cari nama constraint exact dari information_schema
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_TYPE = 'UNIQUE'
        ", [$table]);

        foreach ($constraints as $c) {
            $name = $c->CONSTRAINT_NAME;
            // Drop kalau nama mengandung kolom yang kita target
            if (str_contains($name, $col1) || str_contains($name, 'unique')) {
                try {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                } catch (\Exception $e) {
                    // skip kalau gagal
                }
            }
        }
    }
};