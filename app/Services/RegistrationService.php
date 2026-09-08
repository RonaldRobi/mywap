<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * Senarai pendaftaran ahli (paginated), dengan rekonsiliasi bayaran
     * DOKU pending (had 5) supaya status terkini.
     */
    public function memberRegistrations(User $user): LengthAwarePaginator
    {
        $registrations = Registration::with(['event.organization', 'latestPayment', 'attendance', 'form:id,title'])
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

    // ─── Pendaftaran (kongsi web + API) ───────────────────────────────────────

    /**
     * Peraturan validasi dinamik borang pendaftaran event — sama untuk web
     * dan API supaya tingkah laku tidak terpisah.
     */
    public function validationRules(Form $form): array
    {
        $rules = [
            'answers' => ['required', 'array'],
            'payment_method' => ['nullable', 'in:fpx,duitnow_qr'],
            'ticket_type' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
        ];

        foreach ($form->questions as $q) {
            $key = "answers.{$q->id}";
            $rule = $q->required ? ['required'] : ['nullable'];

            if ($q->type === 'file') {
                $rule[] = 'file';
                $rule[] = 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx,zip';
                $rule[] = 'max:10240';
            } elseif ($q->type === 'email') {
                $rule[] = 'email';
            } elseif ($q->type === 'number') {
                $rule[] = 'numeric';
            } elseif ($q->type === 'phone') {
                $rule[] = 'string';
                $rule[] = 'max:50';
            } elseif ($q->type === 'date') {
                $rule[] = 'date';
            }

            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * Penguatkuasaan tier berbayar: kategori wajib sah + dokumen jika
     * diperlukan. Dibuang sebagai ValidationException supaya Laravel
     * (web) dan client (API) menerima mesej yang sama.
     */
    public function enforceTiers(Form $form, array $data): void
    {
        if (! $form->payment_required || ! $form->hasTiers()) {
            return;
        }

        $ticketType = $data['ticket_type'] ?? null;

        if (! $ticketType || ! $form->tierByLabel($ticketType)) {
            throw ValidationException::withMessages([
                'ticket_type' => 'Sila pilih kategori yuran yang sah.',
            ]);
        }

        if ($form->tierRequiresDocument($ticketType) && empty($data['document'])) {
            throw ValidationException::withMessages([
                'document' => 'Sila muat naik dokumen sokongan (cth. kad pelajar).',
            ]);
        }
    }

    /**
     * Proses penuh pendaftaran event — cipta Registration + FormResponse +
     * FormAnswers, kemudian kendalikan bayaran (gateway / dummy / percuma).
     *
     * Web & API kongsi kaedah ini; beza hanya pada cara hasil dipulangkan.
     *
     * @return array{status: 'success'|'redirect'|'error', registration: Registration, payment_url?: string|null, message?: string|null}
     */
    public function submitForEvent(Form $form, Event $event, array $data, ?User $user): array
    {
        $this->enforceTiers($form, $data);

        $participant = $this->mapAnswersToParticipant($form, $data['answers'] ?? []);

        $ticketType = $data['ticket_type'] ?? null;
        $amount = $form->hasTiers()
            ? ($form->priceForTier($ticketType) ?: 0)
            : (float) $form->price;
        $documentPath = ! empty($data['document'])
            ? $data['document']->store('registration-documents', 'public')
            : null;

        $registration = DB::transaction(function () use ($form, $event, $data, $user, $participant, $ticketType, $documentPath) {
            $registration = Registration::create([
                'event_id' => $event->id,
                'form_id' => $form->id,
                'user_id' => $user?->id,
                'organization_id' => $user?->current_organization_id
                    ?? $form->organization_id,
                'member_no' => $user?->member_no,
                'name' => $participant['name'] ?: ($user?->name ?? 'Peserta'),
                'email' => $participant['email'] ?: $user?->email,
                'phone' => $participant['phone'] ?: $user?->phone,
                'ic_number' => $participant['ic_number'] ?: $user?->ic_number,
                'ticket_type' => $ticketType,
                'document_path' => $documentPath,
                'status' => $form->payment_required ? RegistrationStatus::Pending : RegistrationStatus::Confirmed,
            ]);

            $response = FormResponse::create([
                'form_id' => $form->id,
                'user_id' => $user?->id,
                'respondent_name' => $registration->name,
                'respondent_email' => $registration->email,
                'respondent_phone' => $registration->phone,
                'submitted_at' => now(),
            ]);

            foreach ($data['answers'] as $questionId => $value) {
                $question = $form->questions->firstWhere('id', (int) $questionId);
                $stored = $this->storeAnswerValue($question, $value);

                FormAnswer::create([
                    'form_response_id' => $response->id,
                    'form_question_id' => $questionId,
                    'value' => $stored,
                ]);
            }

            return $registration;
        });

        // Borang percuma: terus sahkan + hantar emel.
        if (! $form->payment_required || ! $amount || $amount <= 0) {
            $registration->confirmAndNotify();

            return [
                'status' => 'success',
                'registration' => $registration,
                'message' => 'Pendaftaran berjaya! No Pendaftaran: '.$registration->registration_no,
            ];
        }

        return $this->createPayment($registration, $form, $event, $data['payment_method'] ?? 'fpx', (float) $amount);
    }

    /**
     * Cipta Payment dan pulangkan URL gateway (atau sahkan dalam mod dummy).
     */
    protected function createPayment(Registration $registration, Form $form, Event $event, string $paymentMethod, float $amount): array
    {
        $org = $form->organization
            ?? ($event->organization_id ? $event->organization : null);

        $useGateway = $org ? $this->gateways->isLive($org) : false;

        $payment = $registration->payments()->create([
            'user_id' => $registration->user_id,
            'amount' => $amount,
            'status' => $useGateway ? 'pending' : 'successful',
            'reference' => $useGateway ? 'REG-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => 'Pendaftaran: '.$event->title,
            'gateway' => $org ? $this->gateways->gatewayFor($org) : 'dummy',
            'organization_id' => $org?->id,
            'channel' => $paymentMethod,
        ]);

        // Jejak rujukan pembayaran dalam session supaya gateway redirect balik
        // (tanpa invoice_number) boleh kenal pasti pendaftaran pengguna ini.
        session(['last_payment_reference' => $payment->reference]);

        if ($useGateway && $org) {
            $url = $this->gateways->createPaymentRedirect(
                $org,
                $payment,
                $registration->name,
                $registration->email ?: ($registration->name.'@mywap.my'),
                $registration->phone,
                'Pendaftaran: '.$event->title,
                $paymentMethod,
            );

            if ($url) {
                return [
                    'status' => 'redirect',
                    'payment_url' => $url,
                    'registration' => $registration,
                ];
            }

            $payment->update(['status' => 'failed']);

            return [
                'status' => 'error',
                'registration' => $registration,
                'message' => 'Pembayaran gagal diproses. Sila cuba lagi.',
            ];
        }

        // Mod dummy (tiada gateway dikonfigurasi): anggap berjaya + hantar emel.
        $registration->confirmAndNotify();

        return [
            'status' => 'success',
            'registration' => $registration,
            'message' => 'Pendaftaran berjaya! No Pendaftaran: '.$registration->registration_no,
        ];
    }

    /**
     * Petakan jawapan borang kepada maklumat peserta (nama/emel/telefon/IC).
     * Form Builder ialah single source of truth — tiada field peserta auto.
     */
    protected function mapAnswersToParticipant(Form $form, array $answers): array
    {
        $result = ['name' => null, 'email' => null, 'phone' => null, 'ic_number' => null];

        foreach ($form->questions as $q) {
            $value = $answers[$q->id] ?? null;

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $value = trim((string) $value);
            $label = strtolower($q->label);

            if ($q->type === 'email') {
                $result['email'] = $value;

                continue;
            }

            if ($q->type === 'phone' || str_contains($label, 'telefon') || str_contains($label, 'phone') || str_contains($label, 'whatsapp')) {
                $result['phone'] ??= $value;

                continue;
            }

            if (str_contains($label, 'kad pengenalan') || str_contains($label, 'no ic') || str_contains($label, 'ic number') || str_contains($label, 'nric')) {
                $result['ic_number'] ??= $value;

                continue;
            }

            if (in_array($q->type, ['text', 'textarea'], true) && $result['name'] === null) {
                $result['name'] = $value;
            }
        }

        return $result;
    }

    protected function storeAnswerValue(?FormQuestion $question, mixed $value): string
    {
        if ($question && $question->type === 'file' && $value instanceof UploadedFile) {
            return $value->store('form-uploads', 'public');
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
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
