<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSetupSeeder extends Seeder
{
    public string $tenantId;
    public int $userId;

    public function setContext(string $tenantId, int $userId): self
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        return $this;
    }

    public function run(): void
    {
        $this->seedAccounts();
        $this->seedPermissionsAndRoles();
    }

    protected function seedAccounts(): void
    {
        $accounts = [
            ['code' => '1-10001', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1-10003', 'name' => 'Piutang Dagang', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1-20001', 'name' => 'Persediaan Barang Dagang', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '2-10001', 'name' => 'Hutang Dagang', 'type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '3-10001', 'name' => 'Modal', 'type' => 'EQUITY', 'normal_balance' => 'CREDIT'],
            ['code' => '4-10001', 'name' => 'Penjualan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['code' => '4-20001', 'name' => 'Pendapatan Lain-lain', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['code' => '5-10001', 'name' => 'HPP', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT'],
            ['code' => '5-20001', 'name' => 'Beban Operasional', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->insert([
                'tenant_id' => $this->tenantId,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'normal_balance' => $account['normal_balance'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedPermissionsAndRoles(): void
    {
        $permissions = [
            'view_dashboard',
            'manage_master_data',
            'manage_products',
            'manage_customers',
            'manage_suppliers',
            'manage_sales_orders',
            'manage_delivery_orders',
            'manage_purchase_orders',
            'manage_goods_receipts',
            'manage_inventory',
            'manage_stock_opname',
            'manage_cash_in',
            'manage_cash_out',
            'manage_expenses',
            'manage_accounting',
            'manage_pos',
            'manage_users',
            'view_sales_report',
            'view_stock_report',
            'view_financial_report',
            'view_profit_loss',
            'view_balance_sheet',
            'view_cash_flow',
            'manage_settings',
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $id = DB::table('permissions')->insertGetId([
                'name' => $perm,
                'guard_name' => 'web',
                'tenant_id' => $this->tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionIds[] = $id;
        }

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissionIds as $permId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id' => $roleId,
                'tenant_id' => $this->tenantId,
            ]);
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'App\Models\User',
            'model_id' => $this->userId,
            'tenant_id' => $this->tenantId,
        ]);
    }
}