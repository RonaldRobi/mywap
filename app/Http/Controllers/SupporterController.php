<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Models\Supporter;
use App\Models\User;
use App\Notifications\SupporterInvitationNotification;
use App\Services\SupporterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Pengurusan "Pendukung": direktori orang awam (bukan ahli) yang datang
 * daripada program, import, atau tambah manual, untuk hebahan dan jemputan.
 */
class SupporterController extends Controller
{
    public function index(Request $request, SupporterService $supporters): Response
    {
        $user = $request->user();
        [$isSuperadmin, $search, $programId, $status, $organizationId] = $this->resolveFilters($request);

        $base = $this->baseQuery($request, $isSuperadmin, $organizationId);

        $counts = [
            'total' => (clone $base)->count(),
            'with_email' => (clone $base)->whereNotNull('email')->count(),
            'prospects' => (clone $base)->whereNull('converted_at')->count(),
            'members' => (clone $base)->whereNotNull('converted_at')->count(),
        ];

        $supporters = $this->applyFilters(
            (clone $base)->with([
                'organization:id,name',
                'events:id,title,start_time',
                'convertedUser:id,name,member_no',
            ]),
            $search,
            $programId,
            $status,
        )
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Supporter $supporter) => [
                'id' => $supporter->id,
                'name' => $supporter->name,
                'email' => $supporter->email,
                'phone' => $supporter->phone,
                'ic_number' => $supporter->ic_number,
                'source' => $supporter->source,
                'notes' => $supporter->notes,
                'is_active' => $supporter->is_active,
                'organization_name' => $supporter->organization?->name,
                'converted_at' => $supporter->converted_at?->format('d M Y'),
                'converted_member' => $supporter->convertedUser?->name,
                'converted_member_no' => $supporter->convertedUser?->member_no,
                'last_invited_at' => $supporter->last_invited_at?->format('d M Y'),
                'created_at' => $supporter->created_at?->format('d M Y'),
                'programs' => $supporter->events
                    ->sortByDesc('start_time')
                    ->values()
                    ->map(fn (Event $event) => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'date' => $event->start_time?->format('d M Y'),
                        'source' => $event->pivot?->source,
                    ])
                    ->all(),
            ]);

        $programs = Event::query()
            ->select(['id', 'title', 'start_time'])
            ->whereHas('supporters', function ($query) use ($isSuperadmin, $user, $organizationId) {
                if (! $isSuperadmin) {
                    $query->where('supporters.organization_id', $user->current_organization_id);
                } elseif ($organizationId) {
                    $query->where('supporters.organization_id', $organizationId);
                }
            })
            ->orderByDesc('start_time')
            ->limit(200)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->start_time?->format('d M Y'),
            ]);

        $organizations = $isSuperadmin
            ? Organization::query()->orderBy('min_age')->get(['id', 'name'])
            : collect([['id' => $user->organization?->id, 'name' => $user->organization?->name]]);

        return Inertia::render('Admin/Supporters', [
            'supporters' => $supporters,
            'counts' => $counts,
            'isSuperadmin' => $isSuperadmin,
            'organizations' => $organizations,
            'programs' => $programs,
            'defaultOrganizationId' => $user->current_organization_id,
            'filters' => [
                'search' => $search,
                'program_id' => $programId,
                'status' => $status,
                'organization_id' => $organizationId,
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        [, $search, $programId, $status, $organizationId] = $this->resolveFilters($request);
        $isSuperadmin = $request->user()->hasRole('Superadmin');

        $supporters = $this->applyFilters(
            $this->baseQuery($request, $isSuperadmin, $organizationId)->with([
                'organization:id,name',
                'events:id,title,start_time',
                'convertedUser:id,name,member_no',
            ]),
            $search,
            $programId,
            $status,
        )
            ->orderBy('name')
            ->get();

        $headings = [
            '#', 'Nama', 'Emel', 'No. Telefon', 'No. IC', 'Organisasi', 'Program',
            'Status', 'No. Ahli', 'Sumber', 'Tarikh Dicipta', 'Tarikh Dijemput', 'Nota',
        ];

        $rows = $this->exportRows($supporters);

        return Excel::download(
            new class($rows, $headings) implements FromCollection, ShouldAutoSize, WithHeadings
            {
                public function __construct(
                    private array $rows,
                    private array $headings,
                ) {}

                public function collection()
                {
                    return collect($this->rows);
                }

                public function headings(): array
                {
                    return $this->headings;
                }
            },
            'pendukung-'.now()->format('Y-m-d_His').'.xlsx',
        );
    }

    public function exportPdf(Request $request)
    {
        [, $search, $programId, $status, $organizationId] = $this->resolveFilters($request);
        $isSuperadmin = $request->user()->hasRole('Superadmin');

        $query = $this->applyFilters(
            $this->baseQuery($request, $isSuperadmin, $organizationId),
            $search,
            $programId,
            $status,
        );

        $total = (clone $query)->count();
        $supporters = (clone $query)
            ->with([
                'organization:id,name',
                'events:id,title,start_time',
                'convertedUser:id,name,member_no',
            ])
            ->orderBy('name')
            ->limit(1000)
            ->get();

        $pdf = Pdf::loadView('exports.supporters', [
            'title' => 'Laporan Pendukung',
            'rows' => $this->exportRows($supporters),
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
            'shown' => $supporters->count(),
            'total' => $total,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('pendukung-'.now()->format('Y-m-d_His').'.pdf');
    }

    public function store(Request $request, SupporterService $supporters): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'ic_number' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $orgId = $isSuperadmin
            ? ($data['organization_id'] ?? null)
            : $user->current_organization_id;

        $email = $supporters->normalizeEmail($data['email'] ?? null);
        $phone = $data['phone'] ?? null;
        $icNumber = $data['ic_number'] ?? null;
        $ic = Registration::normalizeIc($icNumber);
        $rawIc = filled($icNumber) ? trim((string) $icNumber) : null;

        if ($email === null && blank($phone) && $rawIc === null) {
            throw ValidationException::withMessages([
                'email' => 'Isi sekurang-kurangnya emel, No. telefon, atau No. IC.',
            ]);
        }

        if ($supporters->isExistingMember($email, $ic, $rawIc)) {
            throw ValidationException::withMessages([
                'email' => 'Individu ini sudah menjadi ahli. Rekod tidak perlu ditambah sebagai Pendukung.',
            ]);
        }

        $duplicate = $supporters->findExisting(
            $orgId,
            $email,
            $ic,
            $rawIc,
            Registration::normalizePhone($phone),
            null,
        );

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'email' => 'Emel, telefon, atau No. IC ini sudah ada dalam senarai Pendukung.',
            ]);
        }

        Supporter::create([
            'organization_id' => $orgId,
            'name' => trim((string) $data['name']),
            'email' => $email,
            'phone' => $phone,
            'ic_number' => $icNumber,
            'source' => Supporter::SOURCE_MANUAL,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Pendukung berjaya ditambah.');
    }

    public function import(Request $request, SupporterService $supporters): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $orgId = $isSuperadmin
            ? ($request->integer('organization_id') ?: null)
            : $user->current_organization_id;

        $memberEmails = User::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip()
            ->all();

        $existing = Supporter::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId), fn ($q) => $q->whereNull('organization_id'))
            ->get(['email', 'ic_normalized']);

        $existingEmails = $existing
            ->pluck('email')
            ->filter()
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip()
            ->all();
        $existingIcs = $existing
            ->pluck('ic_normalized')
            ->filter()
            ->flip()
            ->all();

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'Fail tidak dapat dibaca.']);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        $imported = 0;
        $skipMember = 0;
        $skipExisting = 0;
        $invalid = 0;

        $emailIdx = 0;
        $nameIdx = null;
        $phoneIdx = null;
        $icIdx = null;
        $start = 0;

        if ($rows !== []) {
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
            $knownHeaders = [
                'name', 'nama', 'email', 'emel', 'alamat emel', 'e-mel',
                'phone', 'telefon', 'no telefon', 'no. telefon',
                'ic', 'ic_number', 'no ic', 'no. ic', 'no_ic', 'no. kad pengenalan',
                'no kad pengenalan', 'kad pengenalan',
            ];

            if (array_intersect($header, $knownHeaders) !== []) {
                $emailIdx = $this->columnIndex($header, ['email', 'emel', 'alamat emel', 'e-mel']);
                $nameIdx = $this->columnIndex($header, ['name', 'nama']);
                $phoneIdx = $this->columnIndex($header, ['phone', 'telefon', 'no telefon', 'no. telefon']);
                $icIdx = $this->columnIndex($header, ['ic', 'ic_number', 'no ic', 'no. ic', 'no_ic', 'no. kad pengenalan', 'no kad pengenalan', 'kad pengenalan']);

                $start = 1;
            }
        }

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $email = $emailIdx !== null
                ? $supporters->normalizeEmail($row[$emailIdx] ?? null)
                : null;
            $name = $nameIdx !== null ? trim((string) ($row[$nameIdx] ?? '')) : '';
            $phone = $phoneIdx !== null ? trim((string) ($row[$phoneIdx] ?? '')) : '';
            $icNumber = $icIdx !== null ? trim((string) ($row[$icIdx] ?? '')) : '';
            $ic = Registration::normalizeIc($icNumber);

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;

                continue;
            }

            if ($email === null && $phone === '' && $icNumber === '') {
                $invalid++;

                continue;
            }

            if ($email !== null && isset($memberEmails[$email])) {
                $skipMember++;

                continue;
            }

            if ($supporters->isExistingMember($email, $ic, $icNumber !== '' ? $icNumber : null)) {
                $skipMember++;

                continue;
            }

            $duplicate = ($email !== null && isset($existingEmails[$email]))
                || ($ic !== null && isset($existingIcs[$ic]))
                || $supporters->findExisting($orgId, $email, $ic, $icNumber !== '' ? $icNumber : null, Registration::normalizePhone($phone), $name ?: null) !== null;

            if ($duplicate) {
                $skipExisting++;

                continue;
            }

            Supporter::create([
                'organization_id' => $orgId,
                'name' => $name ?: null,
                'email' => $email,
                'phone' => $phone ?: null,
                'ic_number' => $icNumber ?: null,
                'source' => Supporter::SOURCE_IMPORT,
                'is_active' => true,
            ]);

            if ($email !== null) {
                $existingEmails[$email] = true;
            }
            if ($ic !== null) {
                $existingIcs[$ic] = true;
            }
            $imported++;
        }

        return back()->with(
            'success',
            "Import selesai: {$imported} ditambah, {$skipMember} dilangkau (ahli), {$skipExisting} dilangkau (sudah ada), {$invalid} tidak sah.",
        );
    }

    public function invite(Request $request, SupporterService $supporters): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $recipients = Supporter::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereNull('converted_at')
            ->whereNotNull('email')
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->get()
            ->unique(fn (Supporter $supporter) => mb_strtolower((string) $supporter->email));

        if ($recipients->isEmpty()) {
            return back()->with('error', 'Tiada pendukung dengan emel yang layak dijemput. Pendukung yang sudah menjadi ahli atau tiada emel akan dilangkau.');
        }

        $sent = 0;

        foreach ($recipients as $supporter) {
            try {
                Notification::route('mail', $supporter->email)
                    ->notify(new SupporterInvitationNotification($supporter));

                $supporter->forceFill(['last_invited_at' => now()])->save();
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($sent === 0) {
            return back()->with('error', 'Jemputan gagal dihantar. Sila semak tetapan emel.');
        }

        $skipped = $ids->count() - $sent;

        return back()->with(
            'success',
            "Jemputan dihantar kepada {$sent} pendukung.".($skipped > 0 ? " {$skipped} dilangkau (tiada emel, sudah menjadi ahli, atau luar skop)." : ''),
        );
    }

    public function destroy(Request $request, Supporter $supporter): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Superadmin')
            && (int) $supporter->organization_id !== (int) $user->current_organization_id) {
            abort(403);
        }

        $supporter->delete();

        return back()->with('success', 'Pendukung berjaya dibuang.');
    }

    /**
     * @return array{0: bool, 1: string, 2: int|null, 3: string, 4: int|null}
     */
    private function resolveFilters(Request $request): array
    {
        $isSuperadmin = $request->user()->hasRole('Superadmin');
        $search = trim((string) $request->input('search'));
        $programId = $request->integer('program_id') ?: null;
        $status = (string) $request->input('status', 'all');
        $status = in_array($status, ['all', 'prospect', 'member'], true) ? $status : 'all';
        $organizationId = $isSuperadmin ? ($request->integer('organization_id') ?: null) : null;

        return [$isSuperadmin, $search, $programId, $status, $organizationId];
    }

    private function baseQuery(Request $request, bool $isSuperadmin, ?int $organizationId): Builder
    {
        $user = $request->user();

        return Supporter::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->when($isSuperadmin && $organizationId, fn ($q) => $q->where('organization_id', $organizationId));
    }

    private function applyFilters(Builder $query, string $search, ?int $programId, string $status): Builder
    {
        return $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ic_number', 'like', "%{$search}%");
                });
            })
            ->when($programId, fn ($q) => $q->whereHas('events', fn ($e) => $e->where('events.id', $programId)))
            ->when($status === 'prospect', fn ($q) => $q->whereNull('converted_at'))
            ->when($status === 'member', fn ($q) => $q->whereNotNull('converted_at'));
    }

    private function exportRows($supporters): array
    {
        return $supporters
            ->values()
            ->map(fn (Supporter $supporter, int $index) => [
                $index + 1,
                $supporter->name ?: '-',
                $supporter->email ?: '-',
                $supporter->phone ?: '-',
                $supporter->ic_number ?: '-',
                $supporter->organization?->name ?? 'Platform',
                $supporter->events
                    ->sortBy('start_time')
                    ->pluck('title')
                    ->filter()
                    ->implode('; ') ?: '-',
                $supporter->converted_at ? 'Sudah jadi ahli' : 'Belum jadi ahli',
                $supporter->convertedUser?->member_no ?: '-',
                $this->sourceLabel($supporter->source),
                $supporter->created_at?->format('d/m/Y') ?? '-',
                $supporter->last_invited_at?->format('d/m/Y') ?? '-',
                $supporter->notes ?: '-',
            ])
            ->all();
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            Supporter::SOURCE_PROGRAM => 'Program',
            Supporter::SOURCE_IMPORT => 'Import',
            Supporter::SOURCE_MANUAL => 'Manual',
            default => $source ?: 'Manual',
        };
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $candidates
     */
    private function columnIndex(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header, true);

            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
