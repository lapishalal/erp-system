<?php

namespace App\Observers;

use App\Models\CashOut;
use App\Services\JournalService;

class CashOutObserver
{
    public function created(CashOut $cashOut): void
    {
        $accountId = $cashOut->category?->account_id;
        JournalService::journalCashOut($cashOut->amount, $cashOut->type, $accountId, $cashOut->created_by);
    }
}