<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FeeService;
use Illuminate\Console\Command;

class GenerateFees extends Command
{
    protected $signature = 'fees:generate {year? : Tahun yuran (default: tahun semasa)}';
    protected $description = 'Generate annual membership fee records for all organizations';

    public function handle(FeeService $feeService): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        $this->info("Generating membership fees for year {$year}...");

        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->warn('No organizations found.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($organizations->count());
        $bar->start();

        $total = 0;
        foreach ($organizations as $org) {
            $count = $feeService->generateAnnualFees($org, $year);
            $total += $count;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! {$total} fee records created for {$organizations->count()} organizations.");

        return self::SUCCESS;
    }
}
