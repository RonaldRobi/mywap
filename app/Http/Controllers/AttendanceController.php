<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\AdminService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly AdminService $admin) {}

    /**
     * Endpoint yang tertanam dalam QR code event.
     *
     * - Ahli login → semak pendaftaran + bayaran, rekod kehadiran (method=member).
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

        if ($user && $user->hasRole(['Superadmin', 'Admin'])) {
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
        $registration = $this->admin->findRegistration($event, $user);

        if (! $registration) {
            return Inertia::render('Events/AttendanceError', [
                'event' => $this->serializeEvent($event),
                'message' => 'Anda belum mendaftar untuk event ini. Sila daftar dahulu.',
            ]);
        }

        $error = $this->admin->registrationBlockReason($registration);
        if ($error) {
            return Inertia::render('Events/AttendanceError', [
                'event' => $this->serializeEvent($event),
                'message' => $error,
            ]);
        }

        $this->admin->recordAttendance($registration, 'member');

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

        $registration = Registration::where('event_id', $event->id)
            ->where(function (Builder $q) use ($identifier) {
                $q->where('registration_no', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('ic_number', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->latest()
            ->first();

        if (! $registration) {
            return Inertia::render('Events/GuestCheckin', [
                'event' => $this->serializeEvent($event),
                'attendUrl' => route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]),
                'error' => 'Tiada rekod pendaftaran dijumpai. Sila semak semula maklumat anda.',
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
     * Dashboard kehadiran admin (filter: event, org, status kehadiran).
     */
    public function adminIndex(Request $request): Response
    {
        $data = $this->admin->attendanceIndex($request, $request->user());

        return Inertia::render('Admin/Events/Attendance', $data);
    }

    public function exportExcel(Request $request)
    {
        $rows = $this->buildExportRows($request);

        return Excel::download(new class($rows) implements FromCollection, WithHeadings
        {
            public function __construct(protected array $rows) {}

            public function collection()
            {
                return collect($this->rows)->map(fn ($r, $i) => [
                    $i + 1,
                    $r['name'],
                    $r['member_no'] ?? '—',
                    $r['organization_name'] ?? '—',
                    $r['event_title'] ?? '—',
                    $r['phone'] ?? '—',
                    $r['payment_status'] ?? '—',
                    $r['attended'] ? 'Hadir' : 'Tidak Hadir',
                    $r['attended_at'] ?? '—',
                ]);
            }

            public function headings(): array
            {
                return ['#', 'Nama', 'No Ahli', 'Organisasi', 'Event', 'Telefon', 'Bayaran', 'Kehadiran', 'Masa Hadir'];
            }
        }, 'kehadiran-'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $rows = $this->buildExportRows($request);
        $title = 'Laporan Kehadiran Event';

        $pdf = Pdf::loadView('exports.attendance', [
            'rows' => $rows,
            'title' => $title,
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ]);

        return $pdf->download('kehadiran-'.now()->format('Y-m-d_His').'.pdf');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

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

    protected function buildExportRows(Request $request): array
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $query = Registration::with(['organization', 'event.organization', 'form', 'attendance', 'latestPayment']);

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

        return $query->latest()->limit(5000)->get()->map(fn (Registration $r) => [
            'name' => $r->name,
            'member_no' => $r->member_no,
            'organization_name' => $r->organization?->name ?? $r->event?->organization?->name,
            'event_title' => $r->event?->title,
            'phone' => $r->phone,
            'payment_status' => $r->latestPayment?->status ?? 'paid',
            'attended' => $r->attendance !== null,
            'attended_at' => $r->attendance?->attended_at?->toDateTimeString(),
        ])->all();
    }
}
