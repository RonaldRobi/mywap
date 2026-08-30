<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Organization;
use App\Services\FacilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FacilityBookingController extends Controller
{
    public function __construct(private readonly FacilityService $facilities) {}

    public function manageFacilities(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $facilities = Facility::query()
            ->with(['organization:id,name,slug', 'media'])
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility) => [
                'id' => $facility->id,
                'organization_id' => $facility->organization_id,
                'organization_name' => $facility->organization?->name,
                'name' => $facility->name,
                'description' => $facility->description,
                'location' => $facility->location,
                'type' => $facility->type,
                'price_per_unit' => (float) $facility->price_per_unit,
                'member_price_per_unit' => $facility->member_price_per_unit !== null ? (float) $facility->member_price_per_unit : null,
                'capacity' => $facility->capacity,
                'image_path' => $facility->image_path,
                'is_active' => (bool) $facility->is_active,
                'media' => $facility->media->map(fn ($m) => [
                    'id' => $m->id,
                    'path' => $m->path,
                    'caption' => $m->caption,
                ])->values(),
            ]);

        return Inertia::render('Admin/FacilitiesManage', [
            'isSuperadmin' => $isSuperadmin,
            'defaultOrganizationId' => $user->current_organization_id,
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : collect([[
                    'id' => $user->organization?->id,
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                ]]),
            'facilities' => $facilities,
        ]);
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => [$isSuperadmin ? 'required' : 'nullable', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:hourly,halfday,daily'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'member_price_per_unit' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);

        $imagePath = $request->hasFile('image')
            ? '/storage/'.ltrim($request->file('image')->store('facilities', 'public'), '/')
            : null;

        $facility = Facility::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'type' => $data['type'],
            'price_per_unit' => $data['price_per_unit'],
            'member_price_per_unit' => ($data['member_price_per_unit'] ?? null) !== '' ? ($data['member_price_per_unit'] ?? null) : null,
            'capacity' => $data['capacity'] ?? null,
            'image_path' => $imagePath,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        foreach ($request->file('gallery', []) as $i => $file) {
            $facility->media()->create([
                'path' => '/storage/'.ltrim($file->store('facilities/gallery', 'public'), '/'),
                'type' => 'image',
                'order' => $i,
            ]);
        }

        return back()->with('success', 'Ruang berjaya ditambah.');
    }

    public function updateFacility(Request $request, Facility $facility): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);
        $this->authorizeFacilityAccess($request->user(), $facility);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => [$isSuperadmin ? 'required' : 'nullable', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:hourly,halfday,daily'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'member_price_per_unit' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delete_media' => ['nullable', 'array'],
            'delete_media.*' => ['integer', 'exists:facility_media,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);
        $imagePath = $facility->image_path;

        if ($request->hasFile('image')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($facility->image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $newPath = $request->file('image')->store('facilities', 'public');
            $imagePath = '/storage/'.ltrim($newPath, '/');
        }

        $facility->update([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'type' => $data['type'],
            'price_per_unit' => $data['price_per_unit'],
            'member_price_per_unit' => ($data['member_price_per_unit'] ?? null) !== '' ? ($data['member_price_per_unit'] ?? null) : null,
            'capacity' => $data['capacity'] ?? null,
            'image_path' => $imagePath,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        foreach ($request->input('delete_media', []) as $mediaId) {
            $media = $facility->media()->find($mediaId);
            if ($media) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url($media->path ?? '', PHP_URL_PATH) ?? ''), '/');
                if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                $media->delete();
            }
        }

        $maxOrder = $facility->media()->max('order') ?? 0;
        foreach ($request->file('gallery', []) as $i => $file) {
            $facility->media()->create([
                'path' => '/storage/'.ltrim($file->store('facilities/gallery', 'public'), '/'),
                'type' => 'image',
                'order' => $maxOrder + $i + 1,
            ]);
        }

        return back()->with('success', 'Ruang berjaya dikemas kini.');
    }

    public function destroyFacility(Request $request, Facility $facility): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);
        $this->authorizeFacilityAccess($request->user(), $facility);

        $oldPath = ltrim(str_replace('/storage/', '', parse_url($facility->image_path ?? '', PHP_URL_PATH) ?? ''), '/');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        foreach ($facility->media as $media) {
            $mediaPath = ltrim(str_replace('/storage/', '', parse_url($media->path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($mediaPath !== '' && Storage::disk('public')->exists($mediaPath)) {
                Storage::disk('public')->delete($mediaPath);
            }
        }

        $facility->delete();

        return back()->with('success', 'Ruang berjaya dipadam.');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $historyStatus = trim((string) $request->query('history_status', ''));

        $data = $this->facilities->indexData($user, $historyStatus);

        return Inertia::render('Facilities/Index', [
            'facilities' => $data['facilities'],
            'myBookings' => $data['myBookings'],
            'isMember' => $data['isMember'],
            'authUser' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ] : null,
            'historyFilters' => [
                'status' => in_array($historyStatus, ['pending', 'approved', 'rejected'], true) ? $historyStatus : '',
            ],
            'jumpToHistory' => $request->query('view') === 'history',
        ]);
    }

    public function show(Request $request, Facility $facility): Response
    {
        $user = $request->user();

        $data = $this->facilities->showFacility($facility, $user);

        return Inertia::render('Facilities/Show', [
            'facility' => $data['facility'],
            'bookings' => $data['bookings'],
            'myBookings' => $data['myBookings'],
            'isMember' => $data['isMember'],
            'authUser' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ] : null,
        ]);
    }

    public function store(Request $request, Facility $facility): RedirectResponse
    {
        $user = $request->user();
        $isMember = $user && $user->hasRole('Member');

        // Pentadbir tidak dibenarkan menempah ruang.
        abort_if($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin')), 403);

        $rules = [
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'contact_name' => [$isMember ? 'nullable' : 'required', 'string', 'max:255'],
            'contact_phone' => [$isMember ? 'nullable' : 'required', 'string', 'max:30'],
        ];

        $data = $request->validate($rules);

        $this->facilities->createBooking($user, $facility, $data);

        return back()->with('success', 'Tempahan berjaya dihantar dan sedang menunggu kelulusan admin.');
    }

    public function adminIndex(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user();
        $status = trim((string) $request->query('status', ''));

        $bookings = FacilityBooking::query()
            ->with(['facility.organization:id,name,slug', 'user:id,name,email'])
            ->when(
                ! $user->hasRole('Superadmin'),
                fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id))
            )
            ->when(
                $status !== '',
                fn ($query) => $query->where('booking_status', $status)
            )
            ->latest('start_datetime')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FacilityBooking $booking) => [
                'id' => $booking->id,
                'facility_name' => $booking->facility?->name,
                'organization_name' => $booking->facility?->organization?->name,
                'member_name' => $booking->user?->name,
                'member_email' => $booking->user?->email,
                'contact_name' => $booking->contact_name,
                'contact_phone' => $booking->contact_phone,
                'start_datetime' => $booking->start_datetime?->toDateTimeString(),
                'end_datetime' => $booking->end_datetime?->toDateTimeString(),
                'total_price' => (float) $booking->total_price,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'admin_remarks' => $booking->admin_remarks,
            ]);

        $summary = [
            'pending' => FacilityBooking::query()
                ->when(
                    ! $user->hasRole('Superadmin'),
                    fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id))
                )
                ->where('booking_status', 'pending')
                ->count(),
            'approved' => FacilityBooking::query()
                ->when(
                    ! $user->hasRole('Superadmin'),
                    fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id))
                )
                ->where('booking_status', 'approved')
                ->count(),
            'rejected' => FacilityBooking::query()
                ->when(
                    ! $user->hasRole('Superadmin'),
                    fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id))
                )
                ->where('booking_status', 'rejected')
                ->count(),
        ];

        return Inertia::render('Admin/FacilityBookings', [
            'bookings' => $bookings,
            'filters' => [
                'status' => $status,
            ],
            'summary' => $summary,
        ]);
    }

    public function updateStatus(Request $request, FacilityBooking $facilityBooking): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        if (! $request->user()->hasRole('Superadmin')) {
            abort_if(
                (int) $facilityBooking->facility?->organization_id !== (int) $request->user()->current_organization_id,
                403
            );
        }

        $data = $request->validate([
            'booking_status' => ['required', 'in:approved,rejected'],
            'admin_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (
            $data['booking_status'] === 'approved'
            && $this->facilities->hasBookingConflict(
                $facilityBooking->facility_id,
                $facilityBooking->start_datetime?->toDateTimeString(),
                $facilityBooking->end_datetime?->toDateTimeString(),
                $facilityBooking->id
            )
        ) {
            return back()->withErrors([
                'booking_status' => 'Kelulusan gagal kerana slot ini sudah bertindih dengan tempahan lain.',
            ]);
        }

        $facilityBooking->update([
            'booking_status' => $data['booking_status'],
            'admin_remarks' => $data['admin_remarks'] ?? null,
        ]);

        return back()->with('success', 'Status tempahan berjaya dikemas kini.');
    }

    private function resolveOrganizationId($user, ?int $submittedOrganizationId): int
    {
        if ($user->hasRole('Superadmin')) {
            return (int) ($submittedOrganizationId ?: $user->current_organization_id);
        }

        return (int) $user->current_organization_id;
    }

    private function authorizeFacilityAccess($user, Facility $facility): void
    {
        if ($user->hasRole('Superadmin')) {
            return;
        }

        abort_if((int) $user->current_organization_id !== (int) $facility->organization_id, 403);
    }
}
