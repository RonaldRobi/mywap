<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * RegistrationService
 *
 * Logik tunggal untuk modul Pendaftaran (Registration) — dikongsi oleh
 * WebController (Inertia) dan ApiController (JSON) supaya web & Flutter
 * tidak drift. Rujuk docs/FLUTTER_PLAN.md §4.
 */
class RegistrationService
{
    public function __construct(
        private readonly RegistrationPaymentService $payments,
    ) {}

    /**
     * Senarai pendaftaran ahli (paginated), dengan rekonsiliasi bayaran
     * DOKU pending (had 5) supaya status terkini.
     */
    public function memberRegistrations(User $user): LengthAwarePaginator
    {
        $registrations = Registration::with(['event.organization', 'latestPayment', 'attendance'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $registrations->getCollection()
            ->filter(fn (Registration $r) => $r->latestPayment
                && $r->latestPayment->status === 'pending'
                && $r->latestPayment->gateway === 'doku')
            ->take(5)
            ->each(fn (Registration $r) => $this->payments->reconcileDokuPayment($r->latestPayment));

        return $registrations->through(fn (Registration $r) => $this->serialize($r));
    }

    /**
     * Bentuk JSON pendaftaran — sama untuk web & API.
     */
    public function serialize(Registration $r): array
    {
        return [
            'id' => $r->id,
            'registration_no' => $r->registration_no,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'ic_number' => $r->ic_number,
            'member_no' => $r->member_no,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'ticket_type' => $r->ticket_type,
            'document_path' => $r->document_path,
            'organization_name' => $r->organization?->name,
            'form_title' => $r->form?->title,
            'payment_status' => $r->latestPayment?->status ?? 'paid',
            'attended' => $r->attendance !== null,
            'attended_at' => $r->attendance?->attended_at?->toDateTimeString(),
            'created_at' => $r->created_at?->toDateTimeString(),
            'event' => $r->event ? [
                'id' => $r->event->id,
                'title' => $r->event->title,
                'slug' => $r->event->slug,
                'start_formatted' => $r->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
            ] : null,
        ];
    }
}
