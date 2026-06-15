<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            'view_dashboard',

            // Laporan (Report)
            'view_sales_report',
            'view_stock_report',
            'view_financial_report',
            'view_profit_loss',
            'view_balance_sheet',
            'view_cash_flow',

            // Master Data
            'manage_master_data',
            'manage_products',
            'manage_customers',
            'manage_suppliers',

            // Transaksi Penjualan
            'manage_sales_orders',
            'manage_delivery_orders',
            'manage_sales_invoices',

            // Transaksi Pembelian
            'manage_purchase_orders',
            'manage_goods_receipts',
            'manage_purchase_returns',

            // Inventory
            'manage_inventory',
            'manage_stock_opname',

            // Keuangan
            'manage_cash_in',
            'manage_cash_out',
            'manage_expenses',
            'manage_accounting',

            // User & Setting
            'manage_users',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'Admin' => $permissions,
            'Sales' => ['view_dashboard', 'manage_sales_orders', 'manage_customers', 'view_sales_report'],
            'Gudang' => ['view_dashboard', 'manage_delivery_orders', 'manage_inventory', 'manage_stock_opname', 'view_stock_report'],
            'Finance' => ['view_dashboard', 'manage_purchase_orders', 'manage_goods_receipts', 'manage_cash_in', 'manage_cash_out', 'manage_expenses', 'manage_accounting', 'view_financial_report', 'view_profit_loss', 'view_cash_flow'],
            'Owner' => ['view_dashboard', 'view_sales_report', 'view_stock_report', 'view_financial_report', 'view_profit_loss', 'view_balance_sheet', 'view_cash_flow', 'manage_settings'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}