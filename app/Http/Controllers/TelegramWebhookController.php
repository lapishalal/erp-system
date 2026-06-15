<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secret !== config('services.telegram.webhook_secret')) {
            Log::warning('Telegram webhook: Invalid secret token', ['received' => $secret]);
            return response('Unauthorized', 401);
        }

        $update = $request->all();
        Log::info('Telegram webhook received', ['update' => $update]);

        if (empty($update)) {
            return response('OK');
        }

        try {
            $bot = new TelegramBotFlowService();
            $bot->handleUpdate($update);
            Log::info('Telegram webhook processed successfully');
        } catch (\Exception $e) {
            Log::error('Telegram Bot Error: ' . $e->getMessage(), [
                'update' => $update,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return response('OK');
    }
}