<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Daftar tabel bisnis yang perlu ditambahkan tenant_id.
     * Kalau ada tabel lain di project Anda, tambahkan di array ini.
     */
    protected array $tables = [
        // Master Data
        'brands',
        'categories',
        'customers',
        'suppliers',
        'warehouses',
        'accounts',
        
        // Sales
        'sales_orders',
        'sales_order_details',      // kalau ada tabel detail
        'delivery_orders',
        'delivery_order_details',   // kalau ada
        'sales_invoices',
        'sales_invoice_details',    // kalau ada
        
        // Purchase
        'purchase_orders',
        'purchase_order_details',   // kalau ada
        'goods_receipts',
        'goods_receipt_details',    // kalau ada
        'purchase_returns',
        'purchase_return_details',  // kalau ada
        'purchase_invoices',
        
        // Inventory
        'product_stocks',
        'stock_transactions',
        'stock_opnames',
        'stock_opname_details',     // kalau ada
        
        // Finance / Accounting
        'cash_in',
        'cash_out',
        'expense_categories',
        'chart_of_accounts',
        'journal_entries',
        'journal_entry_details',    // kalau ada
        
        // HR / Payroll
        'employees',
        'payroll_periods',
        'payrolls',
        'employee_loans',
        
        // POS & System
        'pos_transactions',
        'pos_transaction_items',    // kalau ada
        'audit_logs',
    ];

    public function up(): void
    {
        $defaultTenant = DB::table('tenants')->first();

        foreach ($this->tables as $tableName) {
            // Cek tabel ada di database
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Cek kalau sudah punya tenant_id, skip
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            // Tambah kolom tenant_id
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
                $table->index('tenant_id');
            });

            // Update data existing ke tenant default
            if ($defaultTenant) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenant->id]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};