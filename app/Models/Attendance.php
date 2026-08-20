<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance
 *
 * Rekod kehadiran peserta (melalui registration) pada hari event.
 * `method` menandakan bagaimana kehadiran direkodkan:
 *   - member  → scan QR event oleh ahli yang login
 *   - guest   → scan QR event oleh bukan ahli yang dikenal pasti
 *   - manual  → ditanda oleh admin secara manual
 *
 * unique(registration_id) memastikan setiap pendaftaran hanya direkodkan
 * sekali (idempotent — scan berulang tidak menghasilkan rekod duplikat).
 *
 * @property int $id
 * @property int $event_id
 * @property int $registration_id
 * @property string|null $attended_at
 * @property string $method
 */
class Attendance extends Model
{
    protected $fillable = [
        'event_id', 'registration_id', 'attended_at', 'method',
    ];

    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
