<?php

namespace App\Console\Commands;

use App\Services\AdminFeeBackfillService;
use Illuminate\Console\Command;

class BackfillAdminFee extends Command
{
    protected $signature = 'backfill:admin-fee {--confirm : Lepas prompt konfirmasi}';

    protected $description = 'Isi admin_fee & profit bersih untuk order TikTok yang sudah settlement (idempoten).';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $answer = mb_strtolower((string) $this->ask('Menulis tabel sales_orders (admin_fee & profit). Lanjut? [y/N]'));

            if (!in_array($answer, ['y', 'yes'])) {
                $this->info('Dibatalkan.');
                return 0;
            }
        }

        $updated = AdminFeeBackfillService::run();

        $this->info("Selesai. {$updated} order diperbarui.");

        return self::SUCCESS;
    }
}