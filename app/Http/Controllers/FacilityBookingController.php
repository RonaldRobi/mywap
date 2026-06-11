<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FacilityBookingController extends Controller
{
    public function manageFacilities(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $facilities = Facility::query()
            ->with('organization:id,name,slug')
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
                'capacity' => $facility->capacity,
                'image_path' => $facility->image_path,
                'is_active' => (bool) $facility->is_active,
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
            'type' => ['required', 'in:hourly,daily'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolveOrganizationId($user, $data['organization_id'] ?? null);

        $imagePath = $request->hasFile('image')
            ? '/storage/' . ltrim($request->file('image')->store('facilities', 'public'), '/')
            : null;

        Facility::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'type' => $data['type'],
            'price_per_unit' => $data['price_per_unit'],
            'capacity' => $data['capacity'] ?? null,
            'image_path' => $imagePath,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

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
            'type' => ['required', 'in:hourly,daily'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
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
            $imagePath = '/storage/' . ltrim($newPath, '/');
        }

        $facility->update([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'type' => $data['type'],
            'price_per_unit' => $data['price_per_unit'],
            'capacity' => $data['capacity'] ?? null,
            'image_path' => $imagePath,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

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

        $facility->delete();

        return back()->with('success', 'Ruang berjaya dipadam.');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $historyStatus = trim((string) $request->query('history_status', ''));
        $validHistoryStatuses = ['pending', 'approved', 'rejected'];

        $facilities = Facility::query()
            ->with('organization:id,name,slug')
            ->where('is_active', true)
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
                'capacity' => $facility->capacity,
                'image_path' => $facility->image_path,
            ]);

        $myBookings = FacilityBooking::query()
            ->with(['facility.organization:id,name,slug'])
            ->where('user_id', $user->id)
            ->when(
                in_array($historyStatus, $validHistoryStatuses, true),
                fn ($query) => $query->where('booking_status', $historyStatus)
            )
            ->orderByDesc('start_datetime')
            ->limit(20)
            ->get()
            ->map(fn (FacilityBooking $booking) => [
                'id' => $booking->id,
                'facility_id' => $booking->facility_id,
                'facility_name' => $booking->facility?->name ?? 'Ruang Dipadam',
                'organization_name' => $booking->facility?->organization?->name ?? '-',
                'start_datetime' => $booking->start_datetime?->toDateTimeString(),
                'end_datetime' => $booking->end_datetime?->toDateTimeString(),
                'total_price' => (float) $booking->total_price,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'admin_remarks' => $booking->admin_remarks,
            ]);

        return Inertia::render('Facilities/Index', [
            'facilities' => $facilities,
            'myBookings' => $myBookings,
            'historyFilters' => [
                'status' => in_array($historyStatus, $validHistoryStatuses, true) ? $historyStatus : '',
            ],
            'jumpToHistory' => $request->query('view') === 'history',
        ]);
    }

    public function show(Request $request, Facility $facility): Response
    {
        $bookings = FacilityBooking::query()
            ->where('facility_id', $facility->id)
            ->whereIn('booking_status', ['pending', 'approved'])
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (FacilityBooking $booking) => [
                'id' => $booking->id,
                'start_datetime' => $booking->start_datetime?->toISOString(),
                'end_datetime' => $booking->end_datetime?->toISOString(),
                'booking_status' => $booking->booking_status,
            ]);

        $myBookings = FacilityBooking::query()
            ->where('facility_id', $facility->id)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('start_datetime')
            ->limit(10)
            ->get()
            ->map(fn (FacilityBooking $booking) => [
                'id' => $booking->id,
                'start_datetime' => $booking->start_datetime?->toDateTimeString(),
                'end_datetime' => $booking->end_datetime?->toDateTimeString(),
                'total_price' => (float) $booking->total_price,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'admin_remarks' => $booking->admin_remarks,
            ]);

        return Inertia::render('Facilities/Show', [
            'facility' => [
                'id' => $facility->id,
                'organization_id' => $facility->organization_id,
                'organization_name' => $facility->organization?->name,
                'name' => $facility->name,
                'description' => $facility->description,
                'location' => $facility->location,
                'type' => $facility->type,
                'price_per_unit' => (float) $facility->price_per_unit,
                'capacity' => $facility->capacity,
                'image_path' => $facility->image_path,
                'is_active' => $facility->is_active,
            ],
            'bookings' => $bookings,
            'myBookings' => $myBookings,
        ]);
    }

    public function store(Request $request, Facility $facility): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Member'), 403);

        if (! $facility->is_active) {
            return back()->withErrors([
                'facility' => 'Ruang ini tidak aktif untuk tempahan.',
            ]);
        }

        $data = $request->validate([
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
        ]);

        $start = Carbon::parse($data['start_datetime']);
        $end = Carbon::parse($data['end_datetime']);

        if ($this->hasBookingConflict($facility->id, $start->toDateTimeString(), $end->toDateTimeString())) {
            return back()->withErrors([
                'start_datetime' => 'Slot tempahan bertindih dengan tempahan sedia ada (pending/approved). Sila pilih masa lain.',
            ]);
        }

        FacilityBooking::create([
            'facility_id' => $facility->id,
            'user_id' => $request->user()->id,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'total_price' => $this->calculateTotalPrice($facility, $start->diffInMinutes($end)),
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

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
            && $this->hasBookingConflict(
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

    private function hasBookingConflict(int $facilityId, string $requestedStart, string $requestedEnd, ?int $ignoreBookingId = null): bool
    {
        return FacilityBooking::query()
            ->where('facility_id', $facilityId)
            ->whereIn('booking_status', ['pending', 'approved'])
            ->when($ignoreBookingId, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->where(function ($query) use ($requestedStart, $requestedEnd) {
                $query
                    ->where('start_datetime', '<', $requestedEnd)
                    ->where('end_datetime', '>', $requestedStart);
            })
            ->exists();
    }

    private function calculateTotalPrice(Facility $facility, int $durationInMinutes): float
    {
        if ($durationInMinutes <= 0) {
            return 0.0;
        }

        if ($facility->type === 'daily') {
            $units = (int) ceil($durationInMinutes / 1440);

            return round($units * (float) $facility->price_per_unit, 2);
        }

        $units = (int) ceil($durationInMinutes / 60);

        return round($units * (float) $facility->price_per_unit, 2);
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
