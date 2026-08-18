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
        {--owners : Kirim juga ke user Owner/Admin yang sudah link Telegram}';

    protected $description = 'Kirim laporan harian bisnis ke Telegram owner.';

    public function handle(): int
    {
        $service = new DailyReportService();
        $tg = new TelegramService();

        $chatIds = $this->option('chat')
            ? [$this->option('chat')]
            : array_filter([config('services.telegram.chat_id')]);

        if (empty($chatIds)) {
            $this->error('TELEGRAM_CHAT_ID belum diisi di .env');
            return self::FAILURE;
        }

        $tenantQuery = Tenant::query();
        if ($tenantId = $this->option('tenant')) {
            $tenantQuery->where('id', $tenantId);
        }
        $tenants = $tenantQuery->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant aktif.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($tenants as $tenant) {
            $report = $service->buildReport($tenant->id, $tenant->name);
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
                } catch (\Exception $e) {
                    Log::error('daily:report gagal kirim', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
                    $this->error("Gagal kirim ke {$chatId}: " . $e->getMessage());
                }
            }
        }

        $this->info("Selesai. Laporan terkirim ke {$sent} chat.");
        return self::SUCCESS;
    }
}