<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\Registration;
use App\Services\FormService;
use App\Services\PaymentGatewayManager;
use App\Services\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EventRegistrationController — API pendaftaran program (member).
 *
 * Logik pendaftaran dikongsi dengan web melalui RegistrationService.
 */
class EventRegistrationController extends Controller
{
    public function __construct(
        private readonly FormService $forms,
        private readonly RegistrationService $registrations,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * Payload borang pendaftaran untuk satu event (soalan, tier, bayaran).
     */
    public function form(Request $request, Event $event, Form $form): JsonResponse
    {
        abort_unless($form->event_id === $event->id, 404, 'Borang tidak tergolong dalam event ini.');
        abort_unless($form->is_active, 404, 'Borang pendaftaran ini tidak aktif.');
        abort_unless($event->isPublished(), 404, 'Event belum diterbitkan.');
        abort_if($event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

        $user = $request->user();

        $existing = $user
            ? Registration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        $amount = $form->hasTiers() ? $form->priceForTier(null) : (float) $form->price;
        $org = $form->organization ?? ($event->organization_id ? $event->organization : null);
        $paymentGateway = ($form->payment_required && $amount && $amount > 0)
            ? $this->gateways->branding($org)
            : null;

        return ApiResponse::success([
            'form' => $this->forms->publicFormPayload($form),
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'start_formatted' => $event->start_time?->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $event->location_or_link,
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
            ],
            'payment_gateway' => $paymentGateway,
            'my_registration' => $existing ? [
                'registration_no' => $existing->registration_no,
                'status' => $existing->status->value,
                'status_label' => $existing->status->label(),
            ] : null,
        ]);
    }

    /**
     * Hantar pendaftaran (multipart) — cipta Registration + FormResponse +
     * FormAnswers + Payment. Logik kongsi: RegistrationService::submitForEvent.
     */
    public function submit(Request $request, Event $event): JsonResponse
    {
        abort_if($event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

        $user = $request->user();

        $validated = $request->validate(['form_id' => ['required', 'integer']]);

        $form = Form::where('event_id', $event->id)
            ->where('id', (int) $validated['form_id'])
            ->where('is_active', true)
            ->first();

        if (! $form) {
            return ApiResponse::error('Borang pendaftaran tidak sah atau tidak aktif.', status: 404);
        }

        $existing = $user
            ? Registration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        if ($existing) {
            return ApiResponse::error('Anda sudah mendaftar untuk event ini (No: '.$existing->registration_no.').', status: 422);
        }

        $form->load('questions');

        $data = $request->validate($this->registrations->validationRules($form));

        $result = $this->registrations->submitForEvent($form, $event, $data, $user);

        if ($result['status'] === 'redirect') {
            return ApiResponse::success([
                'status' => 'redirect',
                'payment_url' => $result['payment_url'],
                'registration' => $this->serializeRegistration($result['registration']),
            ]);
        }

        if ($result['status'] === 'success') {
            return ApiResponse::success([
                'status' => 'success',
                'message' => $result['message'],
                'registration' => $this->serializeRegistration($result['registration']),
            ]);
        }

        return ApiResponse::error($result['message'] ?? 'Pembayaran gagal diproses. Sila cuba lagi.', status: 502);
    }

    protected function serializeRegistration(Registration $registration): array
    {
        $registration->loadMissing(['event.organization', 'latestPayment', 'attendance', 'form:id,title']);

        return $this->registrations->serialize($registration);
    }
}
