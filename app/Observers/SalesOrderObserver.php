<?php
// app/Observers/SalesOrderObserver.php

namespace App\Observers;

use App\Models\SalesOrder;

class SalesOrderObserver
{
    public function updating(SalesOrder $so): void
    {
        if ($so->isDirty('status')) {
            match($so->status) {
                'APPROVED' => $so->approved_at = now(),
                'CANCELLED' => $so->cancelled_at = now(),
                default => null,
            };
        }
    }
}