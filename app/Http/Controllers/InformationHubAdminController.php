<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\LibraryItem;
use App\Models\Organization;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class InformationHubAdminController extends Controller
{
    public function libraryIndex(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $libraryItems = LibraryItem::query()
            ->with('organization:id,name,slug')
            ->latest()
            ->take(100)
            ->get()
            ->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'organization_id' => $item->organization_id,
                'organization_name' => $item->organization?->name,
                'title' => $item->title,
                'description' => $item->description,
                'file_path' => $item->file_path,
                'cover_image_path' => $item->cover_image_path,
                'category' => $item->category,
                'created_at' => $item->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/LibraryManage', [
            'isSuperadmin' => $isSuperadmin,
            'defaultOrganizationId' => $user->current_organization_id,
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug', 'min_age', 'max_age'])
                : collect([[
                    'id' => $user->organization?->id,
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'min_age' => $user->organization?->min_age,
                    'max_age' => $user->organization?->max_age,
                ]]),
            'libraryItems' => $libraryItems,
        ]);
    }

    public function index(Request $request, FeeService $feeService): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $search = $request->input('search');
        $organizationIdFilter = $request->input('organization_id');
        $roleFilter = $request->input('role');
        $perPage = (int) ($request->input('per_page', 25));
        $perPage = in_array($perPage, [25, 50, 100]) ? $perPage : 25;

        $query = User::query()->with([
            'organization:id,name,color_theme',
            'branch:id,name,organization_id',
            'roles',
            'membershipFees',
            'transitionHistory.fromOrganization',
            'transitionHistory.toOrganization',
            'rsvps.event.organization',
        ]);

        if (! $isSuperadmin) {
            $query->where('current_organization_id', $user->current_organization_id);
        } elseif ($organizationIdFilter) {
            $query->where('current_organization_id', $organizationIdFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('ic_number', 'like', "%{$search}%")
                  ->orWhere('member_no', 'like', "%{$search}%")
                  ->orWhere('original_member_no', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter);
            });
        }

        $year = now()->year;
        $branches = $isSuperadmin
            ? \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : \App\Models\Branch::where('organization_id', $user->current_organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $members = $query->latest()->paginate($perPage)->withQueryString()
            ->through(fn(User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'member_no' => $u->member_no,
                'original_member_no' => $u->original_member_no,
                'ic_number' => $this->maskIcNumber($u->ic_number),
                'phone' => $u->phone,
                'dob' => $u->dob?->format('d M Y'),
                'gender' => $u->gender,
                'marital_status' => $u->marital_status,
                'education_level' => $u->education_level,
                'current_profession' => $u->current_profession,
                'industry' => $u->industry,
                'expertise' => $u->expertise,
                'position' => $u->position,
                'topics' => $u->topics,
                'organization_name' => $u->organization?->name ?? 'Tiada Organisasi',
                'organization_id' => $u->current_organization_id,
                'organization_color' => $u->organization?->color_theme,
                'branch_name' => $u->branch?->name ?? 'Tiada Cawangan',
                'branch_id' => $u->branch_id,
                'locality' => $u->locality,
                'linkedin_url' => $u->linkedin_url,
                'profile_photo_path' => $u->profile_photo_path,
                'address_1' => $u->address_1,
                'address_2' => $u->address_2,
                'postcode' => $u->postcode,
                'city' => $u->city,
                'state' => $u->state,
                'emergency_contact_name' => $u->emergency_contact_name,
                'emergency_contact_phone' => $u->emergency_contact_phone,
                'role' => $u->roles->pluck('name')->first() ?? 'Member',
                'is_active' => true,
                'transition_history' => $u->transitionHistory->map(fn ($h) => [
                    'from' => $h->fromOrganization?->name ?? 'Pendaftaran',
                    'to'   => $h->toOrganization->name,
                    'date' => $h->transitioned_at->format('d M Y'),
                    'color' => $h->toOrganization?->color_theme ?? '#6b7280',
                ])->values(),
                'attended_programs' => $u->rsvps
                    ->where('status', 'attended')
                    ->sortByDesc('attended_at')
                    ->values()
                    ->map(fn ($rsvp) => [
                        'title' => $rsvp->event?->title ?? 'Program tidak wujud',
                        'date'  => $rsvp->attended_at?->format('d M Y'),
                        'year'  => $rsvp->attended_at?->year,
                        'org'   => $rsvp->event?->organization?->name ?? '—',
                        'color' => $rsvp->event?->organization?->color_theme ?? '#6b7280',
                    ]),
                'fee_status' => $u->membershipFees
                    ->where('year', $year)
                    ->first()?->status ?? 'unpaid',
            ]);


        $positions = \App\Models\OrganizationPosition::where('organization_id', $user->current_organization_id)
            ->orderBy('display_order')->get(['id', 'name']);

        $userQuery = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $user->current_organization_id));
        $stats = [
            'total' => (clone $userQuery)->count(),
            'aktif' => (clone $userQuery)->whereNotNull('profile_completed_at')->count(),
            'tidak_aktif' => (clone $userQuery)->whereNull('profile_completed_at')->count(),
        ];

        return Inertia::render('Admin/InformationHubManage', [
            'isSuperadmin' => $isSuperadmin,
            'defaultOrganizationId' => $user->current_organization_id,
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug', 'min_age', 'max_age'])
                : collect([[
                    'id' => $user->organization?->id,
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'min_age' => $user->organization?->min_age,
                    'max_age' => $user->organization?->max_age,
                ]]),
            'members' => $members,
            'branches' => $branches,
            'orgPositions' => $positions,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'organization_id' => $organizationIdFilter,
                'role' => $roleFilter,
                'per_page' => $perPage,
            ]
        ]);
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $authUser = $request->user();
        $isSuperadmin = $authUser->hasRole('Superadmin');

        if (! $isSuperadmin && $user->current_organization_id !== $authUser->current_organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'ic_number' => ['nullable', 'string', 'max:32', \Illuminate\Validation\Rule::unique('users', 'ic_number')->ignore($user->id)],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:lelaki,perempuan'],
            'marital_status' => ['nullable', 'in:bujang,berkahwin,bercerai,duda/janda'],
            'education_level' => ['nullable', 'string', 'max:120'],
            'current_profession' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'topics' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'locality' => ['nullable', 'string', 'max:120'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:5'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'is_public_in_directory' => ['nullable', 'boolean'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profil ahli berjaya dikemas kini.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);

        Announcement::create([
            'organization_id' => $organizationId,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'published_at' => $data['published_at'] ?? now(),
        ]);

        return back()->with('success', 'Pengumuman berjaya diterbitkan.');
    }

    public function togglePinned(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $announcement->organization_id);

        $announcement->update([
            'is_pinned' => ! $announcement->is_pinned,
        ]);

        return back()->with('success', 'Status pinned pengumuman dikemas kini.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $announcement->organization_id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'published_at' => $data['published_at'] ?? $announcement->published_at,
        ]);

        return back()->with('success', 'Pengumuman berjaya dikemas kini.');
    }

    public function destroyAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $announcement->organization_id);

        $announcement->delete();

        return back()->with('success', 'Pengumuman berjaya dipadam.');
    }

    public function storeLibraryItem(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);

        $path = $request->file('pdf_file')->store('library', 'public');
        $coverPath = $request->hasFile('cover_image')
            ? '/storage/' . ltrim($request->file('cover_image')->store('library/covers', 'public'), '/')
            : null;

        LibraryItem::create([
            'organization_id' => $organizationId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'Umum',
            'file_path' => '/storage/' . ltrim($path, '/'),
            'cover_image_path' => $coverPath,
        ]);

        return back()->with('success', 'Dokumen PDF berjaya dimuat naik.');
    }

    public function storeMember(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);

        $normalizedIcNumber = Str::upper(
            preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? ''
        );
        $request->merge(['ic_number' => $normalizedIcNumber]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'], // Removed unique constraint
            'ic_number' => ['required', 'string', 'max:32', 'unique:users,ic_number'],
            'phone' => ['nullable', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $dob = $request->date('dob');
        $organization = $dob ? Organization::forAge($dob->age) : null;
        $organization ??= Organization::query()->orderBy('min_age')->first();

        $user = User::withoutGlobalScopes()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'ic_number' => $data['ic_number'],
            'phone' => $data['phone'] ?? null,
            'dob' => $dob,
            'current_organization_id' => $organization?->id,
            'password' => Hash::make($data['password'] ?: 'password123'),
        ]);

        if (Role::query()->where('name', 'Member')->where('guard_name', 'web')->exists()) {
            $user->assignRole('Member');
        }

        return back()->with('success', 'Ahli baharu berjaya ditambah.');
    }

    public function destroyLibraryItem(Request $request, LibraryItem $libraryItem): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $libraryItem->organization_id);

        $path = ltrim(str_replace('/storage/', '', parse_url($libraryItem->file_path, PHP_URL_PATH) ?? ''), '/');

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $coverPath = ltrim(str_replace('/storage/', '', parse_url($libraryItem->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');

        if ($coverPath !== '' && Storage::disk('public')->exists($coverPath)) {
            Storage::disk('public')->delete($coverPath);
        }

        $libraryItem->delete();

        return back()->with('success', 'Dokumen PDF berjaya dipadam.');
    }

    public function updateLibraryItem(Request $request, LibraryItem $libraryItem): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $libraryItem->organization_id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'pdf_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);

        $filePath = $libraryItem->file_path;
        $coverImagePath = $libraryItem->cover_image_path;

        if ($request->hasFile('pdf_file')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($libraryItem->file_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $newPath = $request->file('pdf_file')->store('library', 'public');
            $filePath = '/storage/' . ltrim($newPath, '/');
        }

        if ($request->hasFile('cover_image')) {
            $oldCoverPath = ltrim(str_replace('/storage/', '', parse_url($libraryItem->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($oldCoverPath !== '' && Storage::disk('public')->exists($oldCoverPath)) {
                Storage::disk('public')->delete($oldCoverPath);
            }

            $newCoverPath = $request->file('cover_image')->store('library/covers', 'public');
            $coverImagePath = '/storage/' . ltrim($newCoverPath, '/');
        }

        $libraryItem->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'Umum',
            'file_path' => $filePath,
            'cover_image_path' => $coverImagePath,
        ]);

        return back()->with('success', 'Dokumen PDF berjaya dikemas kini.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $user->current_organization_id ?? 0);

        $data = $request->validate([
            'role' => ['required', 'string', 'in:Admin,Member'],
        ]);

        // Prevent Superadmin from being demoted or others from becoming Superadmin through this route
        if ($user->hasRole('Superadmin') || $data['role'] === 'Superadmin') {
            abort(403, 'Akses ditolak.');
        }

        // We only allow syncing Admin or Member roles here.
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Peranan ahli berjaya dikemas kini.');
    }

    public function updateIcNumber(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);

        $normalizedIcNumber = Str::upper(
            preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? ''
        );
        $request->merge(['ic_number' => $normalizedIcNumber]);

        $data = $request->validate([
            'ic_number' => ['required', 'string', 'max:32', 'unique:users,ic_number,' . $user->id],
        ]);

        $user->update([
            'ic_number' => $data['ic_number'],
        ]);

        return back()->with('success', 'No IC/Passport berjaya dikemas kini.');
    }

    public function importStart(Request $request)
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $organization = \App\Models\Organization::find($request->organization_id);
        $prefix = '';
        if ($organization->slug === 'wadah') $prefix = 'W';
        elseif ($organization->slug === 'abim') $prefix = 'A';
        elseif ($organization->slug === 'pkpim') $prefix = 'P';

        $filePath = $request->file('excel_file')->store('imports', 'local');
        return response()->json(['filename' => $filePath, 'prefix' => $prefix]);
    }

    public function importChunk(Request $request)
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);
        $request->validate([
            'filename' => ['required', 'string'],
            'organization_id' => ['required', 'integer'],
            'prefix' => ['nullable', 'string'],
            'start_row' => ['required', 'integer'],
            'chunk_size' => ['required', 'integer'],
        ]);

        ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            $import = new \App\Imports\MembersImport(
                $request->organization_id, 
                $request->prefix ?? '', 
                $request->start_row, 
                $request->chunk_size
            );
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->filename, 'local');
            
            return response()->json([
                'processed' => $import->processedCount,
                'errors' => $import->errors,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CHUNK IMPORT ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function importFinish(Request $request)
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);
        if ($request->filename && \Illuminate\Support\Facades\Storage::disk('local')->exists($request->filename)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($request->filename);
        }
        return response()->json(['success' => true]);
    }

    private function resolveOrganizationId($user, ?int $submittedOrganizationId): int
    {
        if ($user->hasRole('Superadmin')) {
            return $submittedOrganizationId ?: (int) $user->current_organization_id;
        }

        return (int) $user->current_organization_id;
    }

    private function authorizeOrganizationAccess($user, int $organizationId): void
    {
        if ($user->hasRole('Superadmin')) {
            return;
        }

        abort_if((int) $user->current_organization_id !== (int) $organizationId, 403);
    }

    private function maskIcNumber(?string $icNumber): string
    {
        if (! $icNumber) {
            return '-';
        }

        $normalized = preg_replace('/\s+/', '', trim($icNumber)) ?? '';

        if ($normalized === '') {
            return '-';
        }

        $visiblePrefix = mb_substr($normalized, 0, 6);
        $maskedLength = max(0, mb_strlen($normalized) - 6);

        return $visiblePrefix . str_repeat('*', $maskedLength);
    }
}
