<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EventRsvp
 *
 * Pivot between events and users.
 * Status flow: going | maybe | declined → attended (triggered by QR scan).
 *
 * @property int                  $id
 * @property int                  $event_id
 * @property int                  $user_id
 * @property string               $status
 * @property \Carbon\Carbon|null  $attended_at
 */
class EventRsvp extends Model
{
    protected $table = 'event_rsvps';

    protected $fillable = [
        'event_id', 'user_id', 'status', 'attended_at',
    ];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    // ─ Scopes ───────────────────────────────────────────────────────────────

    public function scopeAttended($query)
    {
        return $query->where('status', 'attended')->orderBy('attended_at');
    }

    // ─ Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
