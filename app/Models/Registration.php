<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Notifications\RegistrationConfirmationNotification;
use App\Support\NameMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Registration
 *
 * Rekod pendaftaran peserta ke satu event melalui satu borang pendaftaran.
 * Bertindak sebagai "jambatan" antara modul Event, modul Form dan modul
 * Pembayaran — tanpanya, modul-modul itu kekal berasingan.
 *
 * Ahli: data diisi automatik daripada User (name/email/phone, member_no,
 * organization). Bukan ahli: data diisi manual, user_id = null.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $form_id
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property string $registration_no
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $ic_number
 * @property string $status
 */
class Registration extends Model
{
    public const SOURCE_IMPORT = 'import';

    public const SOURCE_FORM = 'form';

    public const SOURCE_WALKIN = 'walkin';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'event_id', 'form_id', 'user_id', 'organization_id', 'member_no',
        'registration_no', 'name', 'email', 'phone', 'ic_number', 'status', 'source',
        'ticket_type', 'document_path', 'confirmation_sent_at',
        'ic_normalized', 'phone_normalized', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'confirmation_sent_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Registration $registration) {
            if (empty($registration->registration_no)) {
                do {
                    $no = 'REG-'.strtoupper(Str::random(8));
                } while (static::where('registration_no', $no)->exists());
                $registration->registration_no = $no;
            }
        });
    }

    // ─── Identity normalisation ────────────────────────────────────────────────

    /**
     * Normalise No. IC to digits only. Keeping this in one place ensures the
     * value stored during CSV import and the value typed at check-in match,
     * even when one side includes dashes or spaces.
     */
    public static function normalizeIc(?string $value): ?string
    {
        $value = trim((string) $value);

        // Scientific notation and masked values (e.g. "9.50329E+11",
        // "************") are not usable identities: the original digits are
        // already lost, so matching on them merges different people.
        if ($value === '' || preg_match('/[eE][+-]?\d+/', $value) || str_contains($value, '*')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        // Malaysian ICs are 12 digits. Passports and other IDs stay usable
        // through their raw ic_number value instead.
        return strlen((string) $digits) === 12 ? $digits : null;
    }

    /**
     * Canonical Malaysian phone form: digits only, always with the local 0.
     *
     * "123456789", "0123456789", "60123456789" and "+60 12-345 6789" all
     * become "0123456789", so a participant can enter their number with or
     * without the leading zero at check-in.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60')) {
            $digits = substr($digits, 2);

            if ($digits === '') {
                return null;
            }

            if (! str_starts_with($digits, '0')) {
                $digits = '0'.$digits;
            }
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    protected function icNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'ic_number' => $value,
                'ic_normalized' => static::normalizeIc($value),
            ],
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'phone' => $value,
                'phone_normalized' => static::normalizePhone($value),
            ],
        );
    }

    /**
     * Find an existing registration for manual/public form submissions.
     * A valid IC is authoritative; otherwise phone or email is only used when
     * the name is similar, so couples sharing a phone are not merged.
     */
    public static function findExistingForEvent(
        int $eventId,
        ?string $ic,
        ?string $phone,
        ?string $email,
        ?string $name,
    ): ?Registration {
        $query = static::query()->where('event_id', $eventId);

        $ic = static::normalizeIc($ic);
        $phone = static::normalizePhone($phone);
        $email = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;

        if ($ic !== null) {
            $match = (clone $query)->where('ic_normalized', $ic)->latest()->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($phone !== null) {
            foreach ((clone $query)->where('phone_normalized', $phone)->latest()->limit(5)->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        if ($email !== null) {
            foreach ((clone $query)->whereRaw('LOWER(email) = ?', [$email])->latest()->limit(5)->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Find a registration for an event using whichever identifier the
     * participant typed: registration no, member no, IC, phone or email.
     */
    public static function findForIdentity(int $eventId, string $identifier): ?Registration
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $query = static::query()->where('event_id', $eventId);

        if ($match = (clone $query)->where('registration_no', $identifier)->latest()->first()) {
            return $match;
        }

        if ($match = (clone $query)->where('member_no', $identifier)->latest()->first()) {
            return $match;
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?? '';
        $looksLikeEmail = str_contains($identifier, '@');

        if (! $looksLikeEmail && strlen($digits) >= 6) {
            $ic = static::normalizeIc($identifier);
            if ($ic !== null && $match = (clone $query)->where('ic_normalized', $ic)->latest()->first()) {
                return $match;
            }
        }

        if (! $looksLikeEmail && strlen($digits) >= 7) {
            $phone = static::normalizePhone($identifier);
            if ($phone !== null && $match = (clone $query)->where('phone_normalized', $phone)->latest()->first()) {
                return $match;
            }
        }

        if (str_contains($identifier, '@')) {
            $email = mb_strtolower($identifier);
            if ($match = (clone $query)->whereRaw('LOWER(email) = ?', [$email])->latest()->first()) {
                return $match;
            }
        }

        // Legacy rows created before normalisation columns existed.
        return (clone $query)
            ->where(fn (Builder $q) => $q
                ->where('ic_number', $identifier)
                ->orWhereRaw('UPPER(ic_number) = ?', [mb_strtoupper($identifier)])
                ->orWhere('phone', $identifier))
            ->latest()
            ->first();
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function formResponse(): HasOne
    {
        return $this->hasOne(FormResponse::class, 'registration_id')->latestOfMany();
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'payable_id')
            ->where('payable_type', static::class)
            ->latestOfMany();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->latestPayment?->status === 'successful';
    }

    public function hasAttended(): bool
    {
        return $this->attendance !== null;
    }

    /**
     * Sahkan pendaftaran (pending → confirmed) dan hantar emel pengesahan
     * sekali sahaja (dijaga oleh confirmation_sent_at). Dipanggil selepas
     * bayaran berjaya atau untuk pendaftaran percuma.
     */
    public function confirmAndNotify(): void
    {
        if ($this->status === RegistrationStatus::Pending) {
            $this->update(['status' => RegistrationStatus::Confirmed]);
        }

        // Pendaftaran rasmi bermakna peserta akan hadir. Selaraskan RSVP supaya
        // butiran program tidak bertanya "Saya Akan Hadir" selepas mendaftar.
        if ($this->user_id !== null) {
            $rsvp = EventRsvp::firstOrNew([
                'event_id' => $this->event_id,
                'user_id' => $this->user_id,
            ]);

            if (! in_array($rsvp->status, ['going', 'attended'], true)) {
                $rsvp->status = 'going';
                $rsvp->save();
            }
        }

        if ($this->email && $this->confirmation_sent_at === null) {
            Notification::route('mail', $this->email)
                ->notify(new RegistrationConfirmationNotification($this));

            $this->updateQuietly(['confirmation_sent_at' => now()]);
        }
    }
}
