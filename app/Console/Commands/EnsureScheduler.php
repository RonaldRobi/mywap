<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class EnsureScheduler extends Command
{
    protected $signature = 'app:ensure-scheduler';

    protected $description = 'Pastikan cron Laravel berjalan setiap minit untuk projek ini';

    public function handle(): int
    {
        $result = Process::run(['crontab', '-l']);
        if ($result->failed() && ! str_contains(strtolower($result->errorOutput()), 'no crontab for')) {
            $this->error('Tidak dapat membaca crontab; tiada perubahan dibuat.');

            return self::FAILURE;
        }

        $existing = $result->output();
        foreach (explode("\n", $existing) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, '#')
                && preg_match('/^\*\s+\*\s+\*\s+\*\s+\*\s+/', $line)
                && str_contains($line, base_path())
                && str_contains($line, 'artisan schedule:run')) {
                $this->info('Cron Laravel setiap minit sudah tersedia.');

                return self::SUCCESS;
            }
        }

        $entry = '* * * * * cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg(PHP_BINARY).' artisan schedule:run >> /dev/null 2>&1';
        $updated = rtrim($existing)."\n".$entry."\n";
        $installed = Process::input($updated)->run(['crontab', '-']);
        if ($installed->failed()) {
            $this->error('Cron Laravel tidak dapat dipasang.');

            return self::FAILURE;
        }

        $this->info('Cron Laravel dipasang untuk berjalan setiap minit.');

        return self::SUCCESS;
    }
}
