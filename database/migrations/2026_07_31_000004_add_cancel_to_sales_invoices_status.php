<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales_invoices MODIFY COLUMN status ENUM('UNPAID', 'PARTIAL', 'PAID', 'OVERDUE', 'CANCEL') NOT NULL DEFAULT 'UNPAID'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sales_invoices MODIFY COLUMN status ENUM('UNPAID', 'PARTIAL', 'PAID', 'OVERDUE') NOT NULL DEFAULT 'UNPAID'");
    }
};
