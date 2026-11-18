<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')
            ->whereRaw('LOWER(title) LIKE ?', ['%dialog ummah%'])
            ->orderBy('id')
            ->get(['id', 'title', 'start_time', 'end_time']);

        $this->say('=== DIALOG UMMAH INSPECT ===');

        if ($events->isEmpty()) {
            $this->say('Tiada event padanan.');

            return;
        }

        foreach ($events as $event) {
            $this->say("event#{$event->id} | {$event->title}");
            $this->say("  mula={$event->start_time} tamat={$event->end_time}");

            $regs = DB::table('registrations')
                ->where('event_id', $event->id)
                ->get(['id', 'source', 'status', 'form_id', 'created_at', 'updated_at']);

            $this->say('  daftar='.$regs->count());

            $bySource = $regs->groupBy('source')->map->count()->sortKeys();
            $this->say('  ikut source: '.$bySource->map(fn ($n, $s) => "{$s}={$n}")->implode(' '));

            $byStatus = $regs->groupBy('status')->map->count()->sortKeys();
            $this->say('  ikut status: '.$byStatus->map(fn ($n, $s) => "{$s}={$n}")->implode(' '));

            $withForm = $regs->whereNotNull('form_id')->count();
            $this->say("  ada form_id={$withForm} tanpa form_id=".($regs->count() - $withForm));

            foreach ($regs->groupBy('source') as $source => $rows) {
                $times = $rows->pluck('created_at')->filter()->sort()->values();
                if ($times->isEmpty()) {
                    continue;
                }

                $this->say(sprintf(
                    '  %s created: %s .. %s',
                    $source,
                    $times->first(),
                    $times->last(),
                ));
            }

            $att = DB::table('attendances')->where('event_id', $event->id)->get(['method', 'recorded_by', 'attended_at']);
            $this->say('  kehadiran='.$att->count());
            if ($att->isNotEmpty()) {
                $byMethod = $att->groupBy('method')->map->count()->sortKeys();
                $this->say('  ikut method: '.$byMethod->map(fn ($n, $m) => "{$m}={$n}")->implode(' '));
            }

            $imports = DB::table('registration_imports')
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->get(['id', 'filename', 'total_rows', 'created_count', 'updated_count', 'skipped_count', 'removed_count', 'created_at']);

            $this->say('  import rows='.$imports->count());
            foreach ($imports as $import) {
                $this->say(sprintf(
                    '    import#%d %s rows=%d created=%d updated=%d skipped=%d removed=%d at=%s',
                    $import->id,
                    $import->filename,
                    $import->total_rows,
                    $import->created_count,
                    $import->updated_count,
                    $import->skipped_count,
                    $import->removed_count,
                    $import->created_at,
                ));
            }

            $importRegs = $regs->where('source', 'import');
            if ($importRegs->isNotEmpty()) {
                $minutes = [];
                foreach ($importRegs as $row) {
                    if ($row->created_at === null) {
                        continue;
                    }
                    $key = Carbon::parse($row->created_at)->format('Y-m-d H:i');
                    $minutes[$key] = ($minutes[$key] ?? 0) + 1;
                }
                arsort($minutes);
                $this->say('  import batch (top 8):');
                foreach (array_slice($minutes, 0, 8, true) as $minute => $count) {
                    $this->say("    {$minute} => {$count}");
                }

                $sample = DB::table('registrations')
                    ->where('event_id', $event->id)
                    ->where('source', 'import')
                    ->orderBy('id')
                    ->limit(3)
                    ->get(['id', 'registration_no', 'name', 'raw_data']);

                foreach ($sample as $row) {
                    $keys = array_keys(json_decode((string) $row->raw_data, true) ?? []);
                    $this->say("    sample#{$row->id} {$row->registration_no} | ".mb_substr((string) $row->name, 0, 36).' | kolum: '.implode(' / ', array_slice($keys, 0, 8)));
                }
            }

            $logs = DB::table('activity_logs')
                ->where(function ($query) use ($event) {
                    $query->where('target_type', 'App\\Models\\Event')->where('target_id', $event->id);
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'action', 'description', 'created_at']);

            $this->say('  audit event (10 terakhir):');
            foreach ($logs as $log) {
                $this->say("    #{$log->id} {$log->action} at {$log->created_at} | ".mb_substr((string) $log->description, 0, 80));
            }
        }

        $this->say('=== END INSPECT ===');
    }

    public function down(): void
    {
        //
    }

    private function say(string $line): void
    {
        echo $line.PHP_EOL;
    }
};
