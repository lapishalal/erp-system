<?php

namespace App\Services;

use App\Models\SalesOrder;

class AdminFeeBackfillService
{
    /**
     * Isi ulang admin_fee & profit bersih untuk order TikTok yang sudah settlement,
     * dari selisih total invoice dan total kas yang benar-benar cair (CashIn).
     *
     * Idempotent: menjalankannya berulang kali menghasilkan hasil yang sama.
     *
     * @return int jumlah SO yang diperbarui
     */
    public static function run(): int
    {
        $soIds = SalesOrder::query()
            ->where('source', 'tiktok')
            ->pluck('id');

        $updated = 0;

        foreach ($soIds as $soId) {
            $so = SalesOrder::with(['salesInvoices.cashIns'])->find($soId);

            if (!$so) {
                continue;
            }

            $cashReceived = (float) $so->salesInvoices->sum(
                fn ($inv) => $inv->cashIns->where('type', 'CUSTOMER_PAYMENT')->sum('amount')
            );

            if ($cashReceived <= 0) {
                continue;
            }

            $adminFee = self::computeAdminFee($so);
            $netProfit = round((float) $so->total_amount - (float) $so->total_cost - $adminFee, 2);

            if ((float) $so->admin_fee !== $adminFee || (float) $so->profit !== $netProfit) {
                $so->admin_fee = $adminFee;
                $so->profit = $netProfit;
                $so->saveQuietly();
                $updated++;
            }
        }

        return $updated;
    }

    public static function computeAdminFee(SalesOrder $so): float
    {
        $invoiceTotal = (float) $so->salesInvoices->sum('total');
        $cashReceived = (float) $so->salesInvoices->sum(
            fn ($inv) => $inv->cashIns->where('type', 'CUSTOMER_PAYMENT')->sum('amount')
        );

        return max(0, round($invoiceTotal - $cashReceived, 2));
    }
}