<?php

namespace App\Console\Commands;

use App\Services\TelegramBotFlowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPolling extends Command
{
    protected $signature = 'telegram:polling';
    protected $description = 'Run Telegram bot in polling mode (no webhook needed)';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        $apiUrl = "https://api.telegram.org/bot{$token}";
        $offset = 0;

        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN kosong. Cek .env dan config/services.php');
            return 1;
        }

        $this->info('🤖 Bot polling started...');
        $this->info("Token: " . substr($token, 0, 15) . '...');

        // Test getMe dulu
        $this->info('Testing getMe...');
        $me = Http::get("{$apiUrl}/getMe");
        if ($me->successful()) {
            $this->info('✅ Bot connected: @' . $me->json('result.username'));
        } else {
            $this->error('❌ getMe failed: ' . $me->body());
            return 1;
        }

        while (true) {
            try {
                $this->info('Polling updates (offset=' . $offset . ')...');
                
                $response = Http::timeout(30)->get("{$apiUrl}/getUpdates", [
                    'offset' => $offset,
                    'limit' => 10,
                    'timeout' => 25,
                ]);

                if (!$response->successful()) {
                    $this->error('HTTP Error: ' . $response->status() . ' - ' . $response->body());
                    sleep(5);
                    continue;
                }

                $data = $response->json();
                $updates = $data['result'] ?? [];

                $this->info('Received ' . count($updates) . ' updates');

                if (empty($updates)) {
                    usleep(500000); // 0.5 detik
                    continue;
                }

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->info('Processing update #' . $update['update_id']);

                    try {
                        $bot = new TelegramBotFlowService();
                        $bot->handleUpdate($update);
                        $this->info('✅ Update #' . $update['update_id'] . ' processed');
                    } catch (\Exception $e) {
                        $this->error('❌ Error in update: ' . $e->getMessage());
                        $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
                    }
                }

            } catch (\Exception $e) {
                $this->error('Connection error: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}