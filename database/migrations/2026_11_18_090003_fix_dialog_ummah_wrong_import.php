<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dialog Ummah 2.0 (event 23) tersilap import senarai peserta
     * "DIALOG UMMAH 1.0" pada 2026-10-20 21:35. Import itu mencipta 567
     * pendaftaran baharu. Kekalkan rekod yang benar-benar ada kehadiran,
     * bayaran, item atau respons borang; buang selebihnya.
     */
    public function up(): void
    {
        $event = DB::table('events')
            ->whereRaw('LOWER(title) LIKE ?', ['%dialog ummah 2.0%'])
            ->first(['id', 'title']);

        echo '=== FIX UMMAH ==='.PHP_EOL;

        if ($event === null) {
            echo 'Event tidak dijumpai.'.PHP_EOL;

            return;
        }

        $before = DB::table('registrations')->where('event_id', $event->id)->count();

        $candidates = DB::table('registrations')
            ->where('event_id', $event->id)
            ->where('source', 'import')
            ->where('created_at', '>=', '2026-10-20 21:35:00')
            ->where('created_at', '<', '2026-10-20 21:36:00')
            ->whereNull('form_id')
            ->whereNull('user_id')
            ->pluck('id');

        $keep = collect()
            ->merge(DB::table('attendances')->whereIn('registration_id', $candidates)->pluck('registration_id'))
            ->merge(DB::table('payments')->where('payable_type', 'App\\Models\\Registration')->whereIn('payable_id', $candidates)->pluck('payable_id'))
            ->merge(DB::table('form_responses')->whereIn('registration_id', $candidates)->pluck('registration_id'))
            ->merge(DB::table('registration_items')->whereIn('registration_id', $candidates)->pluck('registration_id'))
            ->unique()
            ->values();

        $deleteIds = $candidates->diff($keep)->values();

        echo "event#{$event->id} sebelum={$before} calon={$candidates->count()} kekal={$keep->count()} buang={$deleteIds->count()}".PHP_EOL;

        if ($deleteIds->isNotEmpty()) {
            foreach ($deleteIds->chunk(200) as $chunk) {
                DB::table('registrations')->whereIn('id', $chunk->all())->delete();
            }

            $importId = DB::table('registration_imports')
                ->where('event_id', $event->id)
                ->where('filename', 'like', '%DIALOG UMMAH 1.0%')
                ->orderByDesc('id')
                ->value('id');

            if ($importId !== null) {
                DB::table('registration_imports')->where('id', $importId)->update([
                    'removed_count' => $deleteIds->count(),
                ]);
            }
        }

        $after = DB::table('registrations')->where('event_id', $event->id)->count();
        $attendance = DB::table('attendances')->where('event_id', $event->id)->count();

        echo "selepas={$after} kehadiran={$attendance}".PHP_EOL;
        echo '=== END FIX ==='.PHP_EOL;
    }

    public function down(): void
    {
        //
    }
};
