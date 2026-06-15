<?php

namespace App\Observers;

use App\Models\CashIn;
use App\Services\JournalService;

class CashInObserver
{
    public function created(CashIn $cashIn): void
    {
        JournalService::journalCashIn($cashIn->amount, $cashIn->type, $cashIn->customer_id, $cashIn->created_by);
    }
}