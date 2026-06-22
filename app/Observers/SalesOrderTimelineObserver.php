<?php
// app/Observers/SalesOrderTimelineObserver.php

namespace App\Observers;

use App\Models\SalesOrder;
use App\Services\SalesOrderTimelineService;
use App\Services\TelegramSalesNotificationService;

class SalesOrderTimelineObserver
{
    public function __construct(
        private SalesOrderTimelineService $timelineService,
        private TelegramSalesNotificationService $telegramService
    ) {}

    public function updated(SalesOrder $so): void
    {
        // Detect stage changes and notify
        $timeline = $this->timelineService->generate($so);
        
        foreach ($timeline['stages'] as $stage) {
            if ($stage['status'] === 'completed' && $stage['date']?->diffInMinutes(now()) < 5) {
                $this->telegramService->notifyStageChange($so, $stage['key'], 'completed');
            }
        }

        // Check overdue
        if ($timeline['is_overdue']) {
            $cacheKey = "overdue_notified_{$so->id}";
            if (!cache()->has($cacheKey)) {
                $this->telegramService->notifyOverdue($so);
                cache()->put($cacheKey, true, now()->addHours(24)); // Notify once per day
            }
        }
    }
}