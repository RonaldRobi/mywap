<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Models\UserTransitionHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = User::withoutGlobalScopes()->find(3);
        if ($ahmad) {
            UserTransitionHistory::firstOrCreate(
                ['user_id' => 3, 'from_organization_id' => null, 'to_organization_id' => 1],
                ['transitioned_at' => Carbon::parse('2016-01-15')]
            );
            UserTransitionHistory::firstOrCreate(
                ['user_id' => 3, 'from_organization_id' => 1, 'to_organization_id' => 2],
                ['transitioned_at' => Carbon::parse('2020-06-20')]
            );

            $events = Event::where('organization_id', 2)->take(3)->get();
            foreach ($events as $event) {
                EventRsvp::firstOrCreate(
                    ['event_id' => $event->id, 'user_id' => 3],
                    ['status' => 'attended', 'attended_at' => $event->start_time ?? now()->subDays(rand(1, 60))]
                );
            }
        }

        // For Nurul Izzah — attended events at PKPIM
        $nurul = User::withoutGlobalScopes()->find(4);
        if ($nurul) {
            $events = Event::where('organization_id', 1)->take(2)->get();
            foreach ($events as $event) {
                EventRsvp::firstOrCreate(
                    ['event_id' => $event->id, 'user_id' => 4],
                    ['status' => 'attended', 'attended_at' => $event->start_time ?? now()->subDays(rand(1, 30))]
                );
            }
        }

        $this->command->info('Demo activity seeded: transitions + attended programs.');
    }
}
