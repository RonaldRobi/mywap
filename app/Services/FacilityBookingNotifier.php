<?php

namespace App\Services;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Notifications\FacilityBookingNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * FacilityBookingNotifier
 *
 * Pusat penghantaran notifikasi bagi aliran tempahan fasiliti. Setiap perubahan
 * keadaan (tempahan baharu, diluluskan, ditolak, bayaran diterima) dihantar ke
 * dua saluran serentak:
 *   1. Database (lonceng dalam-app Flutter) — melalui FacilityBookingNotification.
 *   2. Push FCM (banner peranti) — melalui PushNotificationService.
 */
class FacilityBookingNotifier
{
    public function __construct(private readonly PushNotificationService $push) {}

    /**
     * Tempahan baharu diterima — beritahu pentadbir organisasi pemilik fasiliti
     * supaya dapat menyemak & meluluskan.
     */
    public function notifyAdminsOfNewBooking(FacilityBooking $booking): void
    {
        $orgId = $booking->facility?->organization_id;

        if (! $orgId) {
            return;
        }

        $admins = $this->facilityAdmins($orgId);

        if ($admins->isEmpty()) {
            return;
        }

        $facilityName = $booking->facility?->name ?? 'Kemudahan';
        $who = $booking->user?->name ?? $booking->contact_name ?? 'Ahli';
        $when = $booking->start_datetime;

        $title = 'Tempahan fasiliti baharu';
        $content = "{$facilityName} — {$who} (".$this->shortDateTime($when).'). Sila sahkan kelulusan tempahan.';

        $this->deliver($admins, $title, $content, [
            'type' => 'facility_booking',
            'event' => 'booking_created',
            'booking_id' => $booking->id,
            'facility_id' => $booking->facility_id,
            'facility_name' => $facilityName,
        ]);
    }

    /**
     * Status tempahan penempah berubah (approved/rejected) selepas tindakan admin.
     */
    public function notifyOwnerStatusChanged(FacilityBooking $booking, string $status): void
    {
        $user = $booking->user;

        if (! $user) {
            return;
        }

        $facilityName = $booking->facility?->name ?? 'Kemudahan';
        $start = $booking->start_datetime;

        if ($status === 'approved') {
            $title = 'Tempahan diluluskan';
            $content = "Tempahan {$facilityName} anda (".$this->shortDateTime($start).') telah diluluskan. Bayaran akan dikutip apabila tiba di lokasi.';
        } else {
            $title = 'Tempahan ditolak';
            $content = "Tempahan {$facilityName} anda (".$this->shortDateTime($start).') telah ditolak.';
        }

        if (filled($booking->admin_remarks)) {
            $content .= " Catatan admin: {$booking->admin_remarks}";
        }

        $this->deliver([$user], $title, $content, [
            'type' => 'facility_booking',
            'event' => $status === 'approved' ? 'booking_approved' : 'booking_rejected',
            'booking_id' => $booking->id,
            'facility_id' => $booking->facility_id,
            'facility_name' => $facilityName,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
            'admin_remarks' => $booking->admin_remarks,
        ]);
    }

    /**
     * Bayaran di tempat direkodkan oleh admin selepas penempah tiba.
     */
    public function notifyOwnerPaymentRecorded(FacilityBooking $booking): void
    {
        $user = $booking->user;

        if (! $user) {
            return;
        }

        $facilityName = $booking->facility?->name ?? 'Kemudahan';
        $amount = number_format((float) $booking->total_price, 2);

        $this->deliver([$user], 'Bayaran tempahan diterima', "Bayaran RM{$amount} untuk {$facilityName} telah diterima. Terima kasih!", [
            'type' => 'facility_booking',
            'event' => 'payment_recorded',
            'booking_id' => $booking->id,
            'facility_id' => $booking->facility_id,
            'facility_name' => $facilityName,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
        ]);
    }

    /**
     * Pentadbir organisasi yang mengurus fasiliti (bukan Superadmin platform).
     */
    private function facilityAdmins(int $organizationId): Collection
    {
        $roleNames = Role::whereIn('name', ['Admin', 'org-admin'])
            ->pluck('name')
            ->all();

        if ($roleNames === []) {
            return collect();
        }

        return User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->role($roleNames)
            ->where('current_organization_id', $organizationId)
            ->get(['id']);
    }

    /**
     * Tulis notifikasi dalam-app + hantar push FCM untuk setiap penerima.
     *
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $data
     */
    private function deliver(iterable $users, string $title, string $content, array $data): void
    {
        $recipients = collect($users);

        foreach ($recipients as $user) {
            $user->notify(new FacilityBookingNotification($title, $content, $data));
        }

        $this->push->sendToUsers($recipients->pluck('id')->all(), $title, $content, $data);
    }

    private function shortDateTime(?CarbonInterface $when): string
    {
        return $when?->format('d/m/Y g:ia') ?? 'masa belum ditetapkan';
    }
}
