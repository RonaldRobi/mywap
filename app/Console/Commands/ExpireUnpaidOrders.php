<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--hours=24 : Umur minimum (jam) sebelum dibatalkan}';

    protected $description = 'Batalkan pesanan mall yang belum dibayar dan lepaskan stok';

    public function handle(OrderService $orders): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $expired = $orders->expireUnpaidOrders($hours);

        $this->info("{$expired} pesanan belum dibayar telah dibatalkan.");

        return self::SUCCESS;
    }
}
