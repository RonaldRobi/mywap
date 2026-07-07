<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Jobs\SendAnnouncementJob;
use App\Models\BranchChangeRequest;
use App\Models\BranchTransitionHistory;
use App\Models\LibraryItem;
use App\Models\Organization;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        $branchIdFilter = $request->input('branch_id');
        $feeStatusFilter = $request->input('fee_status');
        $registeredFrom = $request->input('registered_from');
        $registeredTo = $request->input('registered_to');
        $perPage = (int) ($request->input('per_page', 25));
        $perPage = in_array($perPage, [25, 50, 100]) ? $perPage : 25;

        $year = now()->year;

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

        if ($branchIdFilter) {
            $query->where('branch_id', $branchIdFilter);
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

        if ($feeStatusFilter) {
            if ($feeStatusFilter === 'paid') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted']));
            } elseif ($feeStatusFilter === 'life_member') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'life_member'));
            } elseif ($feeStatusFilter === 'exempted') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'exempted'));
            } elseif ($feeStatusFilter === 'due') {
                $query->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted', 'life_member']));
            }
        }

        if ($registeredFrom) {
            $query->where('created_at', '>=', $registeredFrom . ' 00:00:00');
        }

        if ($registeredTo) {
            $query->where('created_at', '<=', $registeredTo . ' 23:59:59');
        }

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
                'is_active' => (bool) $u->is_active,
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
            'aktif' => (clone $userQuery)->where('is_active', true)->count(),
            'tidak_aktif' => (clone $userQuery)->where('is_active', false)->count(),
        ];

        $orgStats = [];
        if ($isSuperadmin) {
            $orgStats = Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                ->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'member_count' => User::where('current_organization_id', $org->id)->count(),
                ])->values();
        }

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
            'orgStats' => $orgStats,
            'filters' => [
                'search' => $search,
                'organization_id' => $organizationIdFilter,
                'role' => $roleFilter,
                'branch_id' => $branchIdFilter,
                'fee_status' => $feeStatusFilter,
                'registered_from' => $registeredFrom,
                'registered_to' => $registeredTo,
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
            'member_no' => $isSuperadmin ? ['nullable', 'string', 'max:12', \Illuminate\Validation\Rule::unique('users', 'member_no')->ignore($user->id)] : ['prohibited'],
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

        $originalMemberNo = $user->getOriginal('member_no');
        $originalBranchId = $user->getOriginal('branch_id');

        if ($isSuperadmin && !empty($validated['member_no'])) {
            $no = Str::upper(trim($validated['member_no']));
            $validated['member_no'] = $no;
            $seq = (int) preg_replace('/^[A-Z]+/', '', $no);
            if ($seq > 0) {
                $validated['member_no_sequence'] = $seq;
            }
        }

        $user->update($validated);

        $desc = 'Profil ahli dikemas kini.';
        if ($isSuperadmin && !empty($validated['member_no']) && $validated['member_no'] !== $originalMemberNo) {
            $desc = "No Ahli ditukar dari {$originalMemberNo} ke {$validated['member_no']}.";
        }

        ActivityLog::create([
            'user_id' => $authUser->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => $desc !== 'Profil ahli dikemas kini.' ? 'update_member_no' : 'update_profile',
            'description' => $desc,
        ]);

        // If admin changed branch directly → bypass: auto-reject pending + record audit
        if (array_key_exists('branch_id', $validated) && $validated['branch_id'] != $originalBranchId) {
            BranchChangeRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'reviewed_by' => $authUser->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => 'Ditukar terus oleh admin.',
                ]);

            BranchTransitionHistory::create([
                'user_id' => $user->id,
                'from_branch_id' => $originalBranchId,
                'to_branch_id' => $validated['branch_id'] ?: null,
                'changed_by' => $authUser->id,
                'change_type' => 'admin_direct',
            ]);
        }

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
            'target_criteria' => ['nullable', 'in:all,unpaid_fees,specific_usrah'],
            'usrah_group_id' => ['nullable', 'integer', 'exists:usrah_groups,id'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);

        if (($data['target_criteria'] ?? 'all') === 'specific_usrah' && empty($data['usrah_group_id'])) {
            return back()->withErrors(['usrah_group_id' => 'Sila pilih kumpulan usrah sasaran.'])->withInput();
        }

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);

        $coverPath = $request->hasFile('cover_image')
            ? '/storage/' . ltrim($request->file('cover_image')->store('announcements/covers', 'public'), '/')
            : null;

        $announcement = Announcement::create([
            'organization_id' => $organizationId,
            'author_id' => $user->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'published_at' => $data['published_at'] ?? now(),
            'cover_image_path' => $coverPath,
            'target_criteria' => $data['target_criteria'] ?? 'all',
            'usrah_group_id' => ($data['target_criteria'] ?? 'all') === 'specific_usrah'
                ? $data['usrah_group_id']
                : null,
        ]);

        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $order => $image) {
                $path = $image->store('announcements/gallery', 'public');
                $announcement->images()->create([
                    'image_path' => '/storage/' . ltrim($path, '/'),
                    'display_order' => $order,
                ]);
            }
        }

        SendAnnouncementJob::dispatch($announcement->id);

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
            'target_criteria' => ['nullable', 'in:all,unpaid_fees,specific_usrah'],
            'usrah_group_id' => ['nullable', 'integer', 'exists:usrah_groups,id'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:announcement_images,id'],
        ]);

        $coverPath = $announcement->cover_image_path;

        if ($request->hasFile('cover_image')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($announcement->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $coverPath = '/storage/' . ltrim($request->file('cover_image')->store('announcements/covers', 'public'), '/');
        } elseif ($request->boolean('remove_cover_image')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($announcement->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $coverPath = null;
        }

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'published_at' => $data['published_at'] ?? $announcement->published_at,
            'cover_image_path' => $coverPath,
            'target_criteria' => $data['target_criteria'] ?? $announcement->target_criteria ?? 'all',
            'usrah_group_id' => ($data['target_criteria'] ?? $announcement->target_criteria ?? 'all') === 'specific_usrah'
                ? ($data['usrah_group_id'] ?? $announcement->usrah_group_id)
                : null,
        ]);

        // Remove gallery images
        if ($removeIds = $request->input('remove_image_ids')) {
            $images = AnnouncementImage::whereIn('id', $removeIds)
                ->where('announcement_id', $announcement->id)
                ->get();
            foreach ($images as $img) {
                $path = ltrim(str_replace('/storage/', '', parse_url($img->image_path ?? '', PHP_URL_PATH) ?? ''), '/');
                if ($path !== '' && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                $img->delete();
            }
        }

        // Add new gallery images
        if ($request->hasFile('gallery_images')) {
            $maxOrder = $announcement->images()->max('display_order') ?? -1;
            foreach ($request->file('gallery_images') as $order => $image) {
                $path = $image->store('announcements/gallery', 'public');
                $announcement->images()->create([
                    'image_path' => '/storage/' . ltrim($path, '/'),
                    'display_order' => $maxOrder + 1 + $order,
                ]);
            }
        }

        return back()->with('success', 'Pengumuman berjaya dikemas kini.');
    }

    public function destroyAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeOrganizationAccess($request->user(), $announcement->organization_id);

        $coverPath = ltrim(str_replace('/storage/', '', parse_url($announcement->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
        if ($coverPath !== '' && Storage::disk('public')->exists($coverPath)) {
            Storage::disk('public')->delete($coverPath);
        }

        foreach ($announcement->images as $img) {
            $path = ltrim(str_replace('/storage/', '', parse_url($img->image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

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

        $prefix = match ($organization?->slug) {
            'pkpim' => 'P',
            'abim' => 'A',
            'wadah' => 'W',
            default => 'M',
        };
        $padding = $prefix === 'W' ? 4 : 5;

        $max = User::where('member_no', 'like', $prefix . '%')
            ->max('member_no_sequence');
        $next = ($max ?? 0) + 1;
        $memberNo = $prefix . str_pad($next, $padding, '0', STR_PAD_LEFT);

        $user = User::withoutGlobalScopes()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'ic_number' => $data['ic_number'],
            'phone' => $data['phone'] ?? null,
            'dob' => $dob,
            'current_organization_id' => $organization?->id,
            'member_no' => $memberNo,
            'member_no_sequence' => $next,
            'original_member_no' => $memberNo,
            'password' => Hash::make($data['password'] ?: 'password123'),
        ]);

        if (Role::query()->where('name', 'Member')->where('guard_name', 'web')->exists()) {
            $user->assignRole('Member');
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'organization_id' => $organization?->id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => 'create_member',
            'description' => "Ahli baharu {$user->name} ({$user->member_no}) ditambah oleh {$request->user()->name}.",
        ]);

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

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => 'update_role',
            'description' => "Peranan ahli ditukar kepada {$data['role']}.",
        ]);

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

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => 'update_ic',
            'description' => 'No IC/Passport dikemas kini.',
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

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $authUser = $request->user();
        $isSuperadmin = $authUser->hasRole('Superadmin');

        if (! $isSuperadmin && $user->current_organization_id !== $authUser->current_organization_id) {
            abort(403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::create([
            'user_id' => $authUser->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => $user->is_active ? 'activate_member' : 'deactivate_member',
            'description' => $user->is_active
                ? 'Ahli diaktifkan semula.'
                : 'Ahli dinyahaktifkan.',
        ]);

        $status = $user->is_active ? 'diaktifkan' : 'dinyahaktifkan';
        return back()->with('success', "Ahli berjaya {$status}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Superadmin'), 403);

        Password::sendResetLink(['email' => $user->email]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => 'reset_password',
            'description' => 'Link reset kata laluan dihantar ke emel ahli.',
        ]);

        return back()->with('success', 'Link reset kata laluan telah dihantar ke emel ahli.');
    }

    public function activityLog(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        if (! $user->hasRole('Superadmin') && $targetUser->current_organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $logs = ActivityLog::with('user:id,name')
            ->where('target_type', User::class)
            ->where('target_id', $targetUser->id)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'action' => $log->action,
                'description' => $log->description,
                'performed_by' => $log->user?->name,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $logs]);
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
