<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DailyReport extends Command
{
    protected $signature = 'daily:report
        {--tenant= : Kirim hanya untuk tenant tertentu (opsional)}
        {--chat= : Override chat_id tujuan}
        {--owners : Kirim juga ke user Owner/Admin yang sudah link Telegram}
        {--preview : Tampilkan isi laporan ke console, tanpa kirim Telegram}';

    protected $description = 'Kirim laporan harian bisnis ke Telegram owner.';

    public function handle(): int
    {
        $service = new DailyReportService();
        $tg = new TelegramService();

        $preview = (bool) $this->option('preview');

        $chatIds = $this->option('chat')
            ? [$this->option('chat')]
            : array_filter([config('services.telegram.chat_id')]);

        if (empty($chatIds) && !$preview) {
            $this->error('TELEGRAM_CHAT_ID belum diisi di .env');
            return self::FAILURE;
        }

        $tenants = $this->resolveTenants();
        if ($tenants->isEmpty()) {
            $this->error('Tidak ditemukan tenant aktif. Pastikan tabel tenants terisi, atau user punya tenant_id.');
            return self::FAILURE;
        }

        $sent = 0;
        foreach ($tenants as $tenant) {
            $this->info('Menyusun laporan untuk tenant: ' . $tenant->name . ' (' . $tenant->id . ')');

            $report = $service->buildReport($tenant->id, $tenant->name);

            if ($preview) {
                $this->line('----------------------------------------');
                $this->line($report);
                $this->line('----------------------------------------');
                continue;
            }

            $targets = $chatIds;

            if ($this->option('owners')) {
                $ownerChats = User::where('tenant_id', $tenant->id)
                    ->whereNotNull('telegram_chat_id')
                    ->where('telegram_notifications', true)
                    ->get()
                    ->filter(fn (User $u) => $u->hasRole(['Owner', 'Admin']))
                    ->pluck('telegram_chat_id')
                    ->toArray();
                $targets = array_values(array_unique(array_merge($targets, $ownerChats)));
            }

            foreach ($targets as $chatId) {
                try {
                    $tg->sendMessage((string) $chatId, $report);
                    $sent++;
                    $this->info("Terkirim ke chat {$chatId}.");
                } catch (\Exception $e) {
                    Log::error('daily:report gagal kirim', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
                    $this->error("Gagal kirim ke {$chatId}: " . $e->getMessage());
                }
            }
        }

        if (!$preview) {
            $this->info("Selesai. Laporan terkirim ke {$sent} chat.");
        }

        return self::SUCCESS;
    }

    /**
     * Temukan tenant untuk laporan:
     * 1. --tenant eksplisit
     * 2. Tabel tenants (aktif)
     * 3. Fallback: tenant_id unik dari user yang punya telegram_chat_id
     */
    private function resolveTenants()
    {
        if ($tenantId = $this->option('tenant')) {
            $tenant = Tenant::find($tenantId);
            return $tenant ? collect([$tenant]) : collect();
        }

        $tenants = Tenant::where('is_active', true)->orderBy('name')->get();
        if ($tenants->isNotEmpty()) {
            return $tenants;
        }

        $tenantIds = User::whereNotNull('tenant_id')
            ->whereNotNull('telegram_chat_id')
            ->distinct()
            ->pluck('tenant_id');

        return Tenant::whereIn('id', $tenantIds)->get();
    }
}
