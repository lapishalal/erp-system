<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_logs', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->foreignId('connection_id')->nullable()->constrained('marketplace_connections')->nullOnDelete();
            $table->string('platform');
            $table->string('event_type');
            $table->string('direction')->default('incoming')->comment('incoming | outgoing');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->integer('http_status')->nullable();
            $table->boolean('is_success')->default(true);
            $table->text('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'platform', 'event_type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_logs');
    }
};