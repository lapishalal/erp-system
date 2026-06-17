<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();        // WMG5XP
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tenant_id', 50)->nullable();  // untuk SaaS multi-tenant
            $table->timestamp('expires_at');             // 30 menit
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->index('code');
            $table->index(['tenant_id', 'is_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_codes');
    }
};