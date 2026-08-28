<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Notifications\RegistrationConfirmationNotification;
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
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'confirmation_sent_at' => 'datetime',
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
