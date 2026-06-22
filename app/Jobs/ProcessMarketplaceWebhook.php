<?php

namespace App\Jobs;

use App\Enums\MarketplacePlatform;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceLog;
use App\Models\MarketplaceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMarketplaceWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];

    public function __construct(
        public string $tenantId,
        public MarketplacePlatform $platform,
        public string $eventType,
        public array $payload
    ) {}

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                match ($this->platform) {
                    MarketplacePlatform::TIKTOK => $this->processTikTok(),
                    MarketplacePlatform::SHOPEE => $this->processShopee(),
                };
            });

            Log::info("Webhook processed successfully", [
                'tenant_id' => $this->tenantId,
                'platform' => $this->platform->value,
                'event' => $this->eventType,
            ]);

        } catch (\Throwable $e) {
            Log::error("ProcessWebhook failed", [
                'tenant_id' => $this->tenantId,
                'platform' => $this->platform->value,
                'event' => $this->eventType,
                'error' => $e->getMessage(),
            ]);

            // Update log terakhir jadi failed
            MarketplaceLog::where('tenant_id', $this->tenantId)
                ->where('platform', $this->platform)
                ->where('event_type', $this->eventType)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first()
                ?->update([
                    'is_success' => false,
                    'error_message' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    private function processTikTok(): void
    {
        $orderId = $this->payload['order_id'] 
            ?? $this->payload['data']['order_id'] 
            ?? null;

        if (!$orderId) {
            Log::info("TikTok webhook: no order_id", ['payload' => $this->payload]);
            return;
        }

        $exists = MarketplaceOrder::where('tenant_id', $this->tenantId)
            ->where('platform', MarketplacePlatform::TIKTOK)
            ->where('platform_order_id', $orderId)
            ->exists();

        if ($exists) {
            Log::info("TikTok order already exists", ['order_id' => $orderId]);
            return;
        }

        MarketplaceOrder::create([
            'tenant_id' => $this->tenantId,
            'connection_id' => $this->getConnectionId(),
            'platform' => MarketplacePlatform::TIKTOK,
            'platform_order_id' => $orderId,
            'platform_order_sn' => $this->payload['order_sn'] ?? $orderId,
            'status' => $this->payload['order_status'] ?? 'pending',
            'synced_at' => now(),
            'raw_payload' => $this->payload,
            'is_mapped' => false,
        ]);

        Log::info("TikTok order saved", ['order_id' => $orderId]);
    }

    private function processShopee(): void
    {
        $data = $this->payload['data'] ?? $this->payload;
        $orderSn = $data['ordersn'] 
            ?? $data['order_sn'] 
            ?? $data['order_id'] 
            ?? null;

        if (!$orderSn) {
            Log::info("Shopee webhook: no order_sn", ['payload' => $this->payload]);
            return;
        }

        $exists = MarketplaceOrder::where('tenant_id', $this->tenantId)
            ->where('platform', MarketplacePlatform::SHOPEE)
            ->where('platform_order_id', $orderSn)
            ->exists();

        if ($exists) {
            Log::info("Shopee order already exists", ['order_sn' => $orderSn]);
            return;
        }

        MarketplaceOrder::create([
            'tenant_id' => $this->tenantId,
            'connection_id' => $this->getConnectionId(),
            'platform' => MarketplacePlatform::SHOPEE,
            'platform_order_id' => $orderSn,
            'platform_order_sn' => $orderSn,
            'status' => $data['status'] ?? 'pending',
            'synced_at' => now(),
            'raw_payload' => $this->payload,
            'is_mapped' => false,
        ]);

        Log::info("Shopee order saved", ['order_sn' => $orderSn]);
    }

    private function getConnectionId(): ?int
    {
        return MarketplaceConnection::where('tenant_id', $this->tenantId)
            ->where('platform', $this->platform)
            ->where('is_active', true)
            ->value('id');
    }
}