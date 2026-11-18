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

        echo '=== UMMAH REPORT ==='.PHP_EOL;

        if ($events->isEmpty()) {
            echo 'Tiada event padanan.'.PHP_EOL;

            return;
        }

        foreach ($events as $event) {
            echo "#{$event->id} {$event->title}".PHP_EOL;
            echo "  {$event->start_time} -> {$event->end_time}".PHP_EOL;

            $regs = DB::table('registrations')
                ->where('event_id', $event->id)
                ->get(['id', 'source', 'status', 'form_id', 'user_id', 'name', 'ic_number', 'phone', 'email', 'created_at']);

            echo '  regs='.$regs->count().' by_source='.$this->counts($regs, 'source').' by_status='.$this->counts($regs, 'status').PHP_EOL;
            echo '  with_form='.$regs->whereNotNull('form_id')->count().' with_user='.$regs->whereNotNull('user_id')->count().PHP_EOL;

            $imports = $regs->where('source', 'import');
            if ($imports->isNotEmpty()) {
                $ids = $imports->pluck('id');
                $att = DB::table('attendances')->whereIn('registration_id', $ids)->count();
                $pay = DB::table('payments')->where('payable_type', 'App\\Models\\Registration')->whereIn('payable_id', $ids)->count();
                $resp = DB::table('form_responses')->whereIn('registration_id', $ids)->count();
                echo "  import regs={$imports->count()} attendance={$att} payments={$pay} form_responses={$resp}".PHP_EOL;

                $minutes = [];
                foreach ($imports as $row) {
                    $minutes[Carbon::parse($row->created_at)->format('Y-m-d H:i')] = ($minutes[Carbon::parse($row->created_at)->format('Y-m-d H:i')] ?? 0) + 1;
                }
                arsort($minutes);
                $parts = [];
                foreach (array_slice($minutes, 0, 5, true) as $minute => $count) {
                    $parts[] = "{$minute}={$count}";
                }
                echo '  import_minutes: '.implode(' ', $parts).PHP_EOL;

                $first = DB::table('registrations')->where('id', $ids->min())->first(['raw_data']);
                $keys = array_keys(json_decode((string) ($first->raw_data ?? ''), true) ?? []);
                echo '  raw_columns: '.implode(' | ', array_slice($keys, 0, 12)).PHP_EOL;

                $dupes = 0;
                foreach ($imports as $row) {
                    $match = $regs->first(function ($other) use ($row) {
                        return $other->source !== 'import' && (
                            ($other->email !== null && $row->email !== null && mb_strtolower(trim($other->email)) === mb_strtolower(trim($row->email)))
                            || ($other->phone !== null && $row->phone !== null && preg_replace('/\D+/', '', $other->phone) === preg_replace('/\D+/', '', $row->phone))
                            || ($other->ic_number !== null && $row->ic_number !== null && preg_replace('/\D+/', '', $other->ic_number) === preg_replace('/\D+/', '', $row->ic_number))
                        );
                    });

                    if ($match !== null) {
                        $dupes++;
                    }
                }
                echo "  import_matching_non_import={$dupes}".PHP_EOL;
            }

            $att = DB::table('attendances')->where('event_id', $event->id)->get(['method']);
            echo '  attendance='.$att->count().' by_method='.$this->counts($att, 'method').PHP_EOL;

            $importRows = DB::table('registration_imports')->where('event_id', $event->id)->orderBy('id')->get();
            foreach ($importRows as $import) {
                echo "  import#{$import->id} {$import->filename} rows={$import->total_rows} created={$import->created_count} updated={$import->updated_count} removed={$import->removed_count} at={$import->created_at}".PHP_EOL;
            }

            $logs = DB::table('activity_logs')->where('target_type', 'App\\Models\\Event')->where('target_id', $event->id)->orderByDesc('id')->limit(4)->get(['action', 'description', 'created_at']);
            foreach ($logs as $log) {
                echo "  log {$log->action} {$log->created_at} ".mb_substr((string) $log->description, 0, 60).PHP_EOL;
            }
        }

        echo '=== END ==='.PHP_EOL;
    }

    public function down(): void
    {
        //
    }

    private function counts($rows, string $key): string
    {
        return $rows->groupBy($key)->map->count()->sortKeys()->map(fn ($n, $k) => "{$k}:{$n}")->implode(',');
    }
};
