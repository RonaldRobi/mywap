<?php

namespace App\Console\Commands;

use App\Models\Donor;
use App\Models\InfaqDonation;
use App\Services\DonorService;
use Illuminate\Console\Command;

class BackfillDonors extends Command
{
    protected $signature = 'app:backfill-donors';
    protected $description = 'Backfill donor records from existing InfaqDonation data';

    public function handle(DonorService $donorService): int
    {
        $this->info('Starting donor backfill...');

        $donations = InfaqDonation::whereNull('donor_id')->get();

        if ($donations->isEmpty()) {
            $this->info('No orphan donations found. All done.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($donations->count());
        $bar->start();

        $created = 0;
        $linked = 0;

        foreach ($donations as $donation) {
            $donor = $donorService->findOrCreate($donation);
            $donation->update(['donor_id' => $donor->id]);

            if ($donor->wasRecentlyCreated) {
                $created++;
            }
            $linked++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $donorService->recalculateAll();

        $this->info("Done. Created {$created} new donors, linked {$linked} donations.");
        return self::SUCCESS;
    }
}
