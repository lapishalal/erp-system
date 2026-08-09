<?php

namespace App\Services;

use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class InvoiceBackfillService
{
    /**
     * Generate invoice otomatis untuk Sales Order yang BELUM memiliki invoice sama sekali.
     * Khusus SO biasa (offline) berstatus COMPLETE — SO CANCEL & DRAFT di-skip.
     *
     * Idempotent: menjalankannya berulang kali menghasilkan hasil yang sama
     * (SO yang sudah punya invoice tidak akan diproses lagi).
     *
     * @return int jumlah invoice yang berhasil dibuat
     */
    public static function run(): int
    {
        return DB::transaction(function () {
            $orders = SalesOrder::query()
                ->with('details')
                ->where('status', 'COMPLETE')
                ->whereDoesntHave('salesInvoices')
                ->get();

            $created = 0;

            foreach ($orders as $so) {
                if ($so->total_amount <= 0 || $so->details->isEmpty()) {
                    continue;
                }

                $invoice = SalesInvoice::create([
                    'invoice_number' => self::uniqueInvoiceNumber(),
                    'date' => $so->date ?? now(),
                    'due_date' => now()->addDays(30),
                    'so_id' => $so->id,
                    'customer_id' => $so->customer_id,
                    'total' => $so->total_amount,
                    'paid_amount' => 0,
                    'status' => 'UNPAID',
                    'notes' => 'Auto-generated invoice (backfill) untuk SO: ' . $so->so_number,
                ]);

                foreach ($so->details as $d) {
                    $invoice->details()->create([
                        'product_id' => $d->product_id,
                        'qty' => $d->qty,
                        'price' => $d->unit_price,
                        'subtotal' => $d->subtotal,
                    ]);
                }

                $created++;
            }

            return $created;
        });
    }

    /**
     * @return int jumlah SO yang akan diproses (tanpa membuat apa pun).
     */
    public static function countPending(): int
    {
        return SalesOrder::query()
            ->where('status', 'COMPLETE')
            ->where('total_amount', '>', 0)
            ->whereDoesntHave('salesInvoices')
            ->whereHas('details')
            ->count();
    }

    protected static function uniqueInvoiceNumber(): string
    {
        do {
            $number = 'INV-' . now()->format('Ymd') . '-' . rand(1000, 9999);
        } while (SalesInvoice::where('invoice_number', $number)->exists());

        return $number;
    }
}