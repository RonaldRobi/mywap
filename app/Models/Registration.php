<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Notifications\RegistrationConfirmationNotification;
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
    protected $fillable = [
        'event_id', 'form_id', 'user_id', 'organization_id', 'member_no',
        'registration_no', 'name', 'email', 'phone', 'ic_number', 'status',
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
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Canonical Malaysian phone form: digits only, with the 60 country prefix
     * converted to a leading 0 so "60123456789" and "0123456789" match.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60') && strlen($digits) > 10) {
            $digits = '0'.substr($digits, 2);
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
            ->where(fn (Builder $q) => $q->where('ic_number', $identifier)->orWhere('phone', $identifier))
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

        if ($this->email && $this->confirmation_sent_at === null) {
            Notification::route('mail', $this->email)
                ->notify(new RegistrationConfirmationNotification($this));

            $this->updateQuietly(['confirmation_sent_at' => now()]);
        }
    }
}
