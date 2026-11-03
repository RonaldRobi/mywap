<?php

namespace App\Console\Commands;

use App\Services\RegistrationService;
use Illuminate\Console\Command;

class ExpireUnpaidRegistrations extends Command
{
    protected $signature = 'registrations:expire-unpaid {--hours=24 : Umur minimum (jam) sebelum dibatalkan}';

    protected $description = 'Batalkan pendaftaran berbayar yang belum dibayar dan lepaskan stok item';

    public function handle(RegistrationService $registrations): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $expired = $registrations->expireUnpaidRegistrations($hours);

        $this->info("{$expired} pendaftaran belum dibayar telah dibatalkan.");

        return self::SUCCESS;
    }
}
