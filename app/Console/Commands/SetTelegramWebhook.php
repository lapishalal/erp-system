<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url}';
    protected $description = 'Set Telegram bot webhook URL';

    public function handle(): int
    {
        $url = $this->argument('url');
        $secret = config('services.telegram.webhook_secret');

        $tg = new TelegramService();
        $ok = $tg->setWebhook($url, $secret);

        if ($ok) {
            $this->info("✅ Webhook berhasil di-set ke: {$url}");
            return 0;
        }

        $this->error("❌ Gagal set webhook.");
        return 1;
    }
}