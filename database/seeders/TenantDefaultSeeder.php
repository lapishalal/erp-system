<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantDefaultSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat tenant default
        $tenantId = (string) Str::uuid();
        
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'MS GLOW LIDYA',
            'slug' => 'default',
            'email' => 'admin@demo.com',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Update users yang tenant_id NULL
        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

        // 3. Update tabel Spatie Permission yang tenant_id NULL
        $permissionTables = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
        
        foreach ($permissionTables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        // 4. Update semua tabel bisnis yang sudah ada (tambahkan sesuai tabel di DB Anda)
        $businessTables = [
            'brands', 'categories', 'products', 'customers', 'suppliers', 
            'warehouses', 'sales_orders', 'delivery_orders', 'purchase_orders',
            'goods_receipts', 'product_stocks', 'stock_transactions', 'stock_opnames',
            'sales_invoices', 'purchase_invoices', 'purchase_returns', 'cash_in', 'cash_out',
            'expense_categories', 'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'employees', 'payroll_periods', 'payrolls', 'employee_loans',
            'pos_transactions', 'pos_transaction_items', 'audit_logs',
        ];

        foreach ($businessTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        $this->command->info("Tenant Default created: {$tenantId}");
        $this->command->info('All NULL tenant_id updated to default tenant.');
    }
}