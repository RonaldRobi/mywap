<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Support\Carbon;

class EventRsvpSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil semua event dan user demo
        $events = Event::all();
        $users = User::whereIn('id', [3, 4])->get(); // Ahmad Firdaus & Nurul Izzah

        foreach ($events as $event) {
            foreach ($users as $user) {
                // Elak duplicate RSVP
                $exists = EventRsvp::where('event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->exists();
                if (! $exists) {
                    EventRsvp::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'status' => 'attended',
                        'attended_at' => Carbon::now()->subDays(rand(1, 5)),
                    ]);
                }
            }
        }
    }
}
