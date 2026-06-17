<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, ?string $tenant = null)
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secret !== config('services.telegram.webhook_secret')) {
            Log::warning('Telegram webhook: Invalid secret', ['tenant' => $tenant, 'received' => $secret]);
            return response('Unauthorized', 401);
        }

        // Set tenant context (untuk SaaS nanti)
        if ($tenant) {
            // config(['app.current_tenant' => $tenant]);
            Log::info('Webhook for tenant', ['tenant' => $tenant]);
        }

        $update = $request->all();
        Log::info('Telegram webhook received', ['tenant' => $tenant, 'update' => $update]);

        try {
            $bot = new TelegramBotFlowService();
            $bot->handleUpdate($update);
            Log::info('Telegram webhook processed OK');
        } catch (\Throwable $e) {
            Log::error('Webhook fatal: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return response('OK');
    }
}