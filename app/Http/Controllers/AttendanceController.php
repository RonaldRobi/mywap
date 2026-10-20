<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Registration;
use App\Services\AdminService;
use App\Services\RegistrationImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * AttendanceController
 *
 * Kehadiran hari event — SATU QR Event. Ahli login check-in terus; bukan ahli
 * dikenal pasti melalui no telefon / IC / token pendaftaran.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
        private readonly RegistrationImportService $registrationImports,
    ) {}

    /**
     * Endpoint yang tertanam dalam QR code event.
     *
     * - Ahli login → rekod kehadiran. Tiada pendaftaran → cipta walk-in
     *   (method=walkin); pendaftaran sedia ada → method=member.
     * - Bukan ahli → papar skrin identifikasi (method=guest selepas dikenal pasti).
     * - Pentadbir → ditolak (tidak boleh "hadir" sebagai peserta).
     */
    public function scan(Request $request, int $id, string $token): Response|RedirectResponse
    {
        $event = Event::with('organization')->findOrFail($id);

        if (! hash_equals($event->attendance_token, $token)) {
            abort(403, 'Token kehadiran tidak sah.');
        }

        $user = $request->user();

        if ($user && $user->hasRole(['Superadmin', 'Admin', 'org-admin'])) {
            abort(403, 'Akaun pentadbir tidak dibenarkan merekod kehadiran program.');
        }

        // Bukan ahli / tidak login → skrin identifikasi.
        if (! $user) {
            return Inertia::render('Events/GuestCheckin', [
                'event' => $this->serializeEvent($event),
                'attendUrl' => route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]),
            ]);
        }

        // Ahli: cari pendaftaran (via user_id, atau fallback padanan emel/telefon).
        // Tiada pendaftaran → cipta walk-in; menayangkan QR poster = sengaja
        // menjemput kehadiran, jadi mengimbas QR itu sendiri adalah kebenaran.
        $registration = $this->admin->findRegistration($event, $user);
        $isWalkIn = $registration === null;

        if ($isWalkIn) {
            $registration = $this->admin->createWalkInRegistration($event, $user);
        }

        $error = $this->admin->registrationBlockReason($registration);
        if ($error) {
            return Inertia::render('Events/AttendanceError', [
                'event' => $this->serializeEvent($event),
                'message' => $error,
            ]);
        }

        $this->admin->recordAttendance($registration, $isWalkIn ? 'walkin' : 'member');

        return Inertia::render('Events/AttendanceSuccess', [
            'event' => $this->serializeEvent($event),
            'registration' => [
                'registration_no' => $registration->registration_no,
                'name' => $registration->name,
            ],
            'memberName' => $user->name,
            'attendedAt' => now()->toISOString(),
        ]);
    }

    /**
     * Bukan ahli menghantar identifikasi (POST ke URL QR yang sama).
     */
    public function guestIdentify(Request $request, int $id, string $token): Response
    {
        $event = Event::with('organization')->findOrFail($id);

        if (! hash_equals($event->attendance_token, $token)) {
            abort(403, 'Token kehadiran tidak sah.');
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
        ]);

        $identifier = trim($data['identifier']);

        $registration = Registration::findForIdentity($event->id, $identifier);

        if (! $registration) {
            return Inertia::render('Events/GuestCheckin', [
                'event' => $this->serializeEvent($event),
                'attendUrl' => route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]),
                'identifier' => $identifier,
                'notFound' => true,
                'error' => 'Tiada rekod dijumpai untuk No. IC/No. telefon tersebut. Sila semak semula, atau daftar sebagai peserta baharu di bawah.',
                'registerAction' => $this->registrationAction($event, false),
            ]);
        }

        $error = $this->admin->registrationBlockReason($registration);
        if ($error) {
            return Inertia::render('Events/GuestCheckin', [
                'event' => $this->serializeEvent($event),
                'attendUrl' => route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]),
                'identifier' => $identifier,
                'error' => $error,
                'registerAction' => $this->registrationAction($event, false),
            ]);
        }

        $this->admin->recordAttendance($registration, 'guest');

        return Inertia::render('Events/AttendanceSuccess', [
            'event' => $this->serializeEvent($event),
            'registration' => [
                'registration_no' => $registration->registration_no,
                'name' => $registration->name,
            ],
            'memberName' => $registration->name,
            'attendedAt' => now()->toISOString(),
        ]);
    }

    /**
     * Tetamu walk-in menghantar nama + telefon + emel.
     *
     * Berbeza daripada [guestIdentify] (yang memadankan pendaftaran sedia ada):
     * di sini peserta TIDAK pernah mendaftar. Rekod walk-in dicipta automatik
     * (user_id = null, status = walkin) dan kehadiran direkodkan terus.
     *
     * Kalau telefon/emel sudah ada pendaftaran untuk event ini, rekod itu
     * diguna semula supaya tiada pendua.
     */
    public function guestWalkIn(Request $request, int $id, string $token): Response
    {
        $event = Event::with('organization')->findOrFail($id);

        if (! hash_equals($event->attendance_token, $token)) {
            abort(403, 'Token kehadiran tidak sah.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $phone = trim($data['phone']);
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        $registration = $this->admin->findGuestRegistration($event, $phone, $email);

        if (! $registration) {
            $registration = $this->admin->createGuestWalkInRegistration($event, [
                'name' => trim($data['name']),
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
            ]);
        }

        $error = $this->admin->registrationBlockReason($registration);
        if ($error) {
            return Inertia::render('Events/GuestCheckin', [
                'event' => $this->serializeEvent($event),
                'attendUrl' => route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]),
                'error' => $error,
            ]);
        }

        $this->admin->recordAttendance($registration, 'walkin');

        return Inertia::render('Events/AttendanceSuccess', [
            'event' => $this->serializeEvent($event),
            'registration' => [
                'registration_no' => $registration->registration_no,
                'name' => $registration->name,
            ],
            'memberName' => $registration->name,
            'attendedAt' => now()->toISOString(),
        ]);
    }

    /**
     * Dashboard kehadiran admin (filter: event, org, status kehadiran).
     */
    public function adminIndex(Request $request): Response
    {
        $data = $this->admin->attendanceIndex($request, $request->user());

        return Inertia::render('Admin/Events/Attendance', $data);
    }

    public function exportExcel(Request $request)
    {
        $data = $this->buildExportData($request, 5000);
        $headings = array_merge(
            ['#', 'Nama', 'No Ahli', 'Organisasi myWAP', 'Event', 'Telefon', 'Bayaran', 'Kehadiran', 'Masa Hadir'],
            array_column($data['form_columns'], 'label'),
        );

        return Excel::download(new class($data['rows'], $headings) implements FromCollection, WithHeadings
        {
            public function __construct(
                protected array $rows,
                protected array $headings,
            ) {}

            public function collection()
            {
                return collect($this->rows)->map(fn ($r, $i) => array_merge([
                    $i + 1,
                    $r['name'],
                    $r['member_no'] ?? '—',
                    $r['organization_name'] ?? '—',
                    $r['event_title'] ?? '—',
                    $r['phone'] ?? '—',
                    $r['payment_status'] ?? '—',
                    $r['attended'] ? 'Hadir' : 'Tidak Hadir',
                    $r['attended_at'] ?? '—',
                ], $r['form_values']));
            }

            public function headings(): array
            {
                return $this->headings;
            }
        }, 'kehadiran-'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '512M');

        $data = $this->buildExportData($request, 500);

        // DomPDF builds the whole document in memory. Keep the number of
        // rendered cells bounded so a wide form or a large event cannot
        // exhaust PHP memory and return a 500.
        $columnCount = 9 + count($data['form_columns']);
        $maxRows = max(50, (int) floor(6000 / $columnCount));
        $rows = array_slice($data['rows'], 0, $maxRows);

        $title = 'Laporan Kehadiran Event';

        $pdf = Pdf::loadView('exports.attendance', [
            'rows' => $rows,
            'formColumns' => $data['form_columns'],
            'title' => $title,
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
            'shown' => count($rows),
            'total' => $data['total'],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('kehadiran-'.now()->format('Y-m-d_His').'.pdf');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Pautan "daftar sekarang" untuk user yang belum berdaftar ketika scan
     * kehadiran. Pulangkan null kalau pendaftaran tidak dibuka (event draft /
     * closed / tiada borang aktif).
     *
     * 1 borang aktif → terus ke borang (member: /events/{slug}/daftar/{form},
     * tetamu: /daftar/{token}). Lebih dari satu → overview (pilih borang).
     */
    protected function registrationAction(Event $event, bool $member): ?array
    {
        if (! $event->isPublished() || $event->isClosed()) {
            return null;
        }

        $activeForms = Form::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'share_token']);

        if ($activeForms->isEmpty()) {
            return null;
        }

        $url = $activeForms->count() === 1
            ? ($member
                ? route('events.register', ['event' => $event->slug, 'form' => $activeForms->first()->id])
                : route('events.register.public', $activeForms->first()->share_token))
            : route('events.show', $event->slug);

        return ['url' => $url];
    }

    protected function serializeEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'location_or_link' => $event->location_or_link,
            'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
            'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
            'color_theme' => $event->organization?->color_theme ?? '#334155',
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, form_columns: array<int, array{id: int, label: string, key: string}>, total: int}
     */
    protected function buildExportData(Request $request, int $limit = 5000): array
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $query = Registration::with([
            'organization', 'event.organization', 'form', 'attendance', 'latestPayment',
            'formResponse.answers.question',
        ]);

        if (! $isSuperadmin) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('event', fn ($q2) => $q2->where('organization_id', $user->current_organization_id));
            });
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->event_id);
        }

        if ($request->filled('org')) {
            $query->where('organization_id', (int) $request->org);
        }

        if ($request->filled('attendance')) {
            if ($request->attendance === 'hadir') {
                $query->whereHas('attendance');
            } elseif ($request->attendance === 'tidak_hadir') {
                $query->whereDoesntHave('attendance');
            }
        }

        if ($request->filled('payment')) {
            if ($request->payment === 'pending') {
                $query->whereHas('latestPayment', fn ($q) => $q->where('status', 'pending'));
            } elseif ($request->payment === 'paid') {
                $query->where(function (Builder $q) {
                    $q->whereHas('latestPayment', fn ($p) => $p->where('status', 'successful'))
                        ->orWhereDoesntHave('latestPayment');
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_no', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_no', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $registrations = $query->latest()->limit($limit)->get();
        $formColumns = $this->formColumnsFor($registrations);

        $rows = $registrations->map(function (Registration $registration) use ($formColumns) {
            $answers = $this->formResponseFor($registration)?->answers?->keyBy('form_question_id') ?? collect();
            $rawData = $registration->raw_data ?? [];

            return [
                'name' => $registration->name,
                'member_no' => $registration->member_no,
                'organization_name' => $registration->organization?->name ?? $registration->event?->organization?->name,
                'event_title' => $registration->event?->title,
                'phone' => $registration->phone,
                'payment_status' => $registration->latestPayment?->status ?? 'paid',
                'attended' => $registration->attendance !== null,
                'attended_at' => $registration->attendance?->attended_at?->toDateTimeString(),
                'form_values' => collect($formColumns)
                    ->map(function (array $column) use ($answers, $rawData) {
                        $value = $answers->get($column['id'])?->value;

                        return $value !== null && $value !== ''
                            ? $value
                            : $this->registrationImports->matchRawValue($rawData, $column['label']);
                    })
                    ->all(),
            ];
        })->all();

        return [
            'rows' => $rows,
            'form_columns' => $formColumns,
            'total' => $total,
        ];
    }

    /**
     * Resolve the form response even when an older response was never linked
     * to its registration through registration_id.
     */
    protected function formResponseFor(Registration $registration): ?FormResponse
    {
        if ($registration->relationLoaded('formResponse')) {
            $response = $registration->getRelation('formResponse');
        } else {
            $response = $registration->formResponse;
        }

        if ($response !== null || ! $registration->form_id) {
            return $response;
        }

        $response = FormResponse::query()
            ->where('form_id', $registration->form_id)
            ->where(function ($query) use ($registration) {
                if ($registration->user_id) {
                    $query->orWhere('user_id', $registration->user_id);
                }
                if ($registration->email) {
                    $query->orWhereRaw('LOWER(respondent_email) = ?', [mb_strtolower($registration->email)]);
                }
                if ($registration->name) {
                    $query->orWhere('respondent_name', $registration->name);
                }
            })
            ->with('answers')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $registration->setRelation('formResponse', $response);

        return $response;
    }

    /**
     * Form questions to append to the attendance report, in form order.
     * Core participant columns already shown by the report are skipped.
     *
     * @param  Collection<int, Registration>  $registrations
     * @return array<int, array{id: int, label: string, key: string}>
     */
    protected function formColumnsFor($registrations): array
    {
        if ($registrations->isEmpty()) {
            return [];
        }

        $eventIds = $registrations->pluck('event_id')->filter()->unique()->values();
        $formIds = $registrations->pluck('form_id')->filter()->unique()->values();

        $forms = Form::query()
            ->with(['questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->where(function ($query) use ($eventIds, $formIds) {
                $query->whereIn('event_id', $eventIds);

                if ($formIds->isNotEmpty()) {
                    $query->orWhereIn('id', $formIds);
                }
            })
            ->orderBy('id')
            ->get();

        $seen = [];
        $columns = [];

        foreach ($forms as $form) {
            foreach ($form->questions as $question) {
                $label = trim((string) $question->label);
                $label = $label !== '' ? $label : 'Soalan '.$question->id;

                if ($this->isAttendanceBaseColumn($this->normalizeColumnLabel($label))) {
                    continue;
                }

                $unique = $label;
                $suffix = 2;

                while (in_array($this->normalizeColumnLabel($unique), $seen, true)) {
                    $unique = $label.' ('.$suffix.')';
                    $suffix++;
                }

                $seen[] = $this->normalizeColumnLabel($unique);
                $columns[] = [
                    'id' => (int) $question->id,
                    'label' => $unique,
                    'key' => $this->normalizeColumnLabel($unique),
                ];
            }
        }

        return $columns;
    }

    protected function isAttendanceBaseColumn(string $label): bool
    {
        if (in_array($label, ['nama', 'no ahli', 'organisasi mywap', 'event', 'telefon', 'bayaran', 'kehadiran', 'masa hadir'], true)) {
            return true;
        }

        if ($this->isCompanyLabel($label)) {
            return false;
        }

        foreach (['nama', 'no ahli', 'telefon'] as $base) {
            if (str_contains($label, $base)) {
                return true;
            }
        }

        return false;
    }

    protected function isCompanyLabel(string $label): bool
    {
        foreach ([
            'syarikat', 'agensi', 'organisasi', 'ngo', 'jabatan', 'institusi',
            'universiti', 'sekolah', 'kilang', 'pertubuhan', 'badan', 'kementerian',
        ] as $word) {
            if (str_contains($label, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeColumnLabel(string $label): string
    {
        $label = strtolower($label);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $label) ?? '');
    }
}
