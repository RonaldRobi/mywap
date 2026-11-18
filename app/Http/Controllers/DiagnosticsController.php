<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\RegistrationDedupeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DiagnosticsController extends Controller
{
    public function dialogUmmah(Request $request, string $token): JsonResponse
    {
        $expected = (string) config('app.deploy_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }

        $events = DB::table('events')
            ->whereRaw('LOWER(title) LIKE ?', ['%dialog ummah%'])
            ->orderBy('id')
            ->get(['id', 'title', 'start_time', 'end_time']);

        $report = [];

        foreach ($events as $event) {
            $regs = DB::table('registrations')
                ->where('event_id', $event->id)
                ->get(['id', 'source', 'status', 'form_id', 'user_id', 'name', 'ic_number', 'phone', 'email', 'created_at']);

            $imports = $regs->where('source', 'import');
            $importIds = $imports->pluck('id');

            $minutes = [];
            foreach ($imports as $row) {
                $key = Carbon::parse($row->created_at)->format('Y-m-d H:i');
                $minutes[$key] = ($minutes[$key] ?? 0) + 1;
            }
            arsort($minutes);

            $first = $importIds->isEmpty()
                ? null
                : DB::table('registrations')->where('id', $importIds->min())->first(['raw_data']);

            $matches = 0;
            foreach ($imports as $row) {
                $match = $regs->first(function ($other) use ($row) {
                    if ($other->source === 'import') {
                        return false;
                    }

                    return ($other->email !== null && $row->email !== null && mb_strtolower(trim($other->email)) === mb_strtolower(trim($row->email)))
                        || ($other->phone !== null && $row->phone !== null && preg_replace('/\D+/', '', $other->phone) === preg_replace('/\D+/', '', $row->phone))
                        || ($other->ic_number !== null && $row->ic_number !== null && preg_replace('/\D+/', '', $other->ic_number) === preg_replace('/\D+/', '', $row->ic_number));
                });

                if ($match !== null) {
                    $matches++;
                }
            }

            $batches = [];
            foreach ($imports->groupBy(fn ($row) => Carbon::parse($row->created_at)->format('Y-m-d H:i')) as $minute => $rows) {
                $batchIds = $rows->pluck('id');
                $batches[$minute] = [
                    'count' => $rows->count(),
                    'with_attendance' => DB::table('attendances')->whereIn('registration_id', $batchIds)->count(),
                    'with_payments' => DB::table('payments')->where('payable_type', 'App\\Models\\Registration')->whereIn('payable_id', $batchIds)->count(),
                    'with_form_responses' => DB::table('form_responses')->whereIn('registration_id', $batchIds)->count(),
                    'with_user' => $rows->whereNotNull('user_id')->count(),
                    'statuses' => $rows->groupBy('status')->map->count()->all(),
                ];
            }

            $summary = app(RegistrationDedupeService::class)->summary(Event::find($event->id));

            $report[] = [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'registrations' => $regs->count(),
                'unique_participants' => $summary['unique_identities'],
                'duplicate_rows' => $summary['duplicate_rows'],
                'by_source' => $regs->groupBy('source')->map->count()->sortKeys()->all(),
                'by_status' => $regs->groupBy('status')->map->count()->sortKeys()->all(),
                'with_form' => $regs->whereNotNull('form_id')->count(),
                'with_user' => $regs->whereNotNull('user_id')->count(),
                'imports' => $imports->count(),
                'import_attendance' => $importIds->isEmpty() ? 0 : DB::table('attendances')->whereIn('registration_id', $importIds)->count(),
                'import_payments' => $importIds->isEmpty() ? 0 : DB::table('payments')->where('payable_type', 'App\\Models\\Registration')->whereIn('payable_id', $importIds)->count(),
                'import_form_responses' => $importIds->isEmpty() ? 0 : DB::table('form_responses')->whereIn('registration_id', $importIds)->count(),
                'import_minutes' => array_slice($minutes, 0, 8, true),
                'created_batches' => $batches,
                'import_raw_columns' => array_slice(array_keys(json_decode((string) ($first->raw_data ?? ''), true) ?? []), 0, 15),
                'import_matching_non_import' => $matches,
                'attendance' => DB::table('attendances')->where('event_id', $event->id)->count(),
                'attendance_by_method' => DB::table('attendances')->where('event_id', $event->id)->get(['method'])->groupBy('method')->map->count()->all(),
                'import_rows' => DB::table('registration_imports')->where('event_id', $event->id)->orderBy('id')->get(['id', 'filename', 'total_rows', 'created_count', 'updated_count', 'removed_count', 'created_at'])->all(),
                'logs' => DB::table('activity_logs')->where('target_type', 'App\\Models\\Event')->where('target_id', $event->id)->orderByDesc('id')->limit(6)->get(['action', 'description', 'created_at'])->all(),
            ];
        }

        return response()->json(['events' => $report]);
    }
}
