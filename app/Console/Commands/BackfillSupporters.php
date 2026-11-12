<?php

namespace App\Console\Commands;

use App\Services\SupporterService;
use Illuminate\Console\Command;

class BackfillSupporters extends Command
{
    protected $signature = 'app:backfill-supporters';

    protected $description = 'Backfill Pendukung records and program links from non-member registrations';

    public function handle(SupporterService $supporters): int
    {
        $this->info('Memproses pendaftaran bukan ahli...');

        $synced = $supporters->backfillFromRegistrations();

        $this->info("Selesai. {$synced} rekod Pendukung dipautkan atau dikemas kini.");

        return self::SUCCESS;
    }
}
