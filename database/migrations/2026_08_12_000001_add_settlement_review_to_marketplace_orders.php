<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->decimal('settlement_amount', 15, 2)->nullable()
                ->comment('Nilai settlement/cair dari TikTok (0 = butuh review manual)');
            $table->boolean('needs_review')->default(false)
                ->comment('Penanda pesanan perlu dicek manual (settlement 0 / retur)');
            $table->string('review_status')->nullable()
                ->comment('pending | retur_confirmed | cancel_confirmed');
            $table->timestamp('reviewed_at')->nullable()
                ->comment('Waktu staff menyelesaikan review');
            $table->unsignedBigInteger('reviewed_by')->nullable()
                ->comment('User yang melakukan review');
            $table->text('review_note')->nullable()
                ->comment('Catatan hasil cek manual (mis. konfirmasi expedisi)');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn([
                'settlement_amount',
                'needs_review',
                'review_status',
                'reviewed_at',
                'reviewed_by',
                'review_note',
            ]);
        });
    }
};