<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\FeeReminderNotification;
use Illuminate\Console\Command;

class NotifyAnnualFees extends Command
{
    protected $signature = 'fees:notify-start-of-year {year? : Tahun yuran (default: tahun semasa)}';

    protected $description = 'Send in-app invoice/reminder notifications to members with unpaid annual fees';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        $this->info("Sending fee reminder notifications for year {$year}...");

        $members = User::withoutGlobalScopes()
            ->with('organization:id,name,fee_amount')
            ->whereHas('membershipFees', fn ($q) => $q->where('year', $year)->where('status', 'unpaid'))
            ->get();

        $sent = 0;
        foreach ($members as $member) {
            $fee = $member->membershipFees->firstWhere('year', $year);

            $amount = (float) ($fee->amount ?? $member->organization?->fee_amount ?? 0);
            $orgName = $member->organization?->name ?? 'Organisasi';

            $member->notify(new FeeReminderNotification($year, $amount, $orgName));
            $sent++;
        }

        $this->info("Done! {$sent} members notified for year {$year}.");

        return self::SUCCESS;
    }
}
