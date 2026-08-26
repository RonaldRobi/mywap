<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * FacilityService
 *
 * Logik tunggal untuk domain Kemudahan (Facility + FacilityBooking) — dikongsi
 * oleh FacilityBookingController (Inertia) dan Api\V1\FacilityController (JSON)
 * supaya web & Flutter tidak drift. Rujuk docs/FLUTTER_PLAN.md §4.
 */
class FacilityService
{
    public function serializeFacility(Facility $facility, bool $includeIsActive = false): array
    {
        $data = [
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
            'media' => $facility->media->map(fn ($m) => [
                'id' => $m->id,
                'path' => $m->path,
                'caption' => $m->caption,
            ])->values(),
        ];

        if ($includeIsActive) {
            $data['is_active'] = $facility->is_active;
        }

        return $data;
    }

    /**
     * Tempahan "saya" ringkas — untuk halaman detail/endpoint show.
     */
    public function serializeMyBooking(FacilityBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'start_datetime' => $booking->start_datetime?->toDateTimeString(),
            'end_datetime' => $booking->end_datetime?->toDateTimeString(),
            'total_price' => (float) $booking->total_price,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
            'admin_remarks' => $booking->admin_remarks,
        ];
    }

    /**
     * Tempahan "saya" dengan maklumat ruang — untuk senarai index.
     */
    public function serializeMyBookingHistory(FacilityBooking $booking): array
    {
        return [
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
        ];
    }

    /**
     * Slot tempahan (tanpa data pengguna) — untuk peta jadual di halaman show.
     */
    public function serializeBookingSlot(FacilityBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'start_datetime' => $booking->start_datetime?->toISOString(),
            'end_datetime' => $booking->end_datetime?->toISOString(),
            'booking_status' => $booking->booking_status,
        ];
    }

    /**
     * Payload untuk senarai kemudahan + sejarah tempahan saya (index web & API).
     */
    public function indexData(?User $user = null, string $historyStatus = ''): array
    {
        $validHistoryStatuses = ['pending', 'approved', 'rejected'];

        $facilities = Facility::query()
            ->with(['organization:id,name,slug', 'media'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility) => $this->serializeFacility($facility));

        $myBookings = collect();
        if ($user) {
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
                ->map(fn (FacilityBooking $booking) => $this->serializeMyBookingHistory($booking));
        }

        return [
            'facilities' => $facilities,
            'myBookings' => $myBookings,
            'isMember' => $user && $user->hasRole('Member'),
        ];
    }

    /**
     * Payload untuk halaman detail satu kemudahan (show web & API).
     */
    public function showFacility(Facility $facility, ?User $user = null): array
    {
        $facility->load(['organization:id,name,slug', 'media']);

        $bookings = FacilityBooking::query()
            ->where('facility_id', $facility->id)
            ->whereIn('booking_status', ['pending', 'approved'])
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (FacilityBooking $booking) => $this->serializeBookingSlot($booking));

        $myBookings = collect();
        if ($user) {
            $myBookings = FacilityBooking::query()
                ->where('facility_id', $facility->id)
                ->where('user_id', $user->id)
                ->orderByDesc('start_datetime')
                ->limit(10)
                ->get()
                ->map(fn (FacilityBooking $booking) => $this->serializeMyBooking($booking));
        }

        return [
            'facility' => $this->serializeFacility($facility, true),
            'bookings' => $bookings,
            'myBookings' => $myBookings,
            'isMember' => $user && $user->hasRole('Member'),
        ];
    }

    /**
     * Cipta tempahan ruang. Pentadbir dilarang; slot bertindih ditolak.
     */
    public function createBooking(?User $user, Facility $facility, array $data): FacilityBooking
    {
        $isMember = $user && $user->hasRole('Member');

        if ($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin'))) {
            abort(403, 'Akaun pentadbir tidak dibenarkan menempah ruang.');
        }

        if (! $facility->is_active) {
            throw ValidationException::withMessages([
                'facility' => 'Ruang ini tidak aktif untuk tempahan.',
            ]);
        }

        $start = Carbon::parse($data['start_datetime']);
        $end = Carbon::parse($data['end_datetime']);

        if ($this->hasBookingConflict($facility->id, $start->toDateTimeString(), $end->toDateTimeString())) {
            throw ValidationException::withMessages([
                'start_datetime' => 'Slot tempahan bertindih dengan tempahan sedia ada (pending/approved). Sila pilih masa lain.',
            ]);
        }

        $contactName = ($data['contact_name'] ?? null) ?: $user?->name;
        $contactPhone = ($data['contact_phone'] ?? null) ?: $user?->phone;

        return FacilityBooking::create([
            'facility_id' => $facility->id,
            'user_id' => $user?->id,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'total_price' => $this->calculateTotalPrice($facility, $start->diffInMinutes($end), $isMember),
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * Semak sama ada slot bertindih dengan tempahan pending/approved.
     */
    public function hasBookingConflict(int $facilityId, string $requestedStart, string $requestedEnd, ?int $ignoreBookingId = null): bool
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

    /**
     * Kira harga tempahan berdasarkan kadar per unit (member boleh dapat diskaun).
     */
    public function calculateTotalPrice(Facility $facility, int $durationInMinutes, bool $isMember = false): float
    {
        if ($durationInMinutes <= 0) {
            return 0.0;
        }

        $rate = $facility->price_per_unit;
        if ($isMember && $facility->member_price_per_unit !== null) {
            $rate = $facility->member_price_per_unit;
        }

        if ($facility->type === 'daily') {
            $units = (int) ceil($durationInMinutes / 1440);

            return round($units * (float) $rate, 2);
        }

        $units = (int) ceil($durationInMinutes / 60);

        return round($units * (float) $rate, 2);
    }
}
