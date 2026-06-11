<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Event
 *
 * Represents a programme belonging to one NGO tier, or global (Semua) when
 * organization_id is null.
 *
 * @property int              $id
 * @property int|null         $organization_id
 * @property string           $title
 * @property string           $slug
 * @property string|null      $description
 * @property string           $type   ('physical'|'online')
 * @property string|null      $location_or_link
 * @property \Carbon\Carbon   $start_time
 * @property \Carbon\Carbon   $end_time
 * @property string|null      $featured_image_path
 * @property string           $attendance_token
 */
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'title', 'slug', 'description', 'type',
        'location_or_link', 'start_time', 'end_time',
        'featured_image_path', 'attendance_token',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time'   => 'datetime',
        ];
    }

    // ─ Hooks ─────────────────────────────────────────────────────────────

    /**
     * Auto-generate a unique attendance_token and URL-safe slug on creation.
     * Generating the token in booted() (not a DB default) ensures it is always
     * available even when records are created via factories or CLI imports.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Event $event) {
            if (empty($event->attendance_token)) {
                do {
                    $token = Str::random(32);
                } while (static::where('attendance_token', $token)->exists());
                $event->attendance_token = $token;
            }

            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title) . '-' . Str::lower(Str::random(6));
            }
        });
    }

    // ─ Accessors ──────────────────────────────────────────────────────────

    /**
     * Build a pre-filled Google Calendar "create event" URL.
     * Dates formatted as YYYYMMDDTHHmmssZ (UTC) as required by the API.
     */
    public function getGoogleCalendarUrlAttribute(): string
    {
        $fmt   = 'Ymd\THis\Z';
        $start = $this->start_time->utc()->format($fmt);
        $end   = $this->end_time->utc()->format($fmt);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $this->title,
            'dates'    => "{$start}/{$end}",
            'details'  => strip_tags((string) $this->description),
            'location' => (string) $this->location_or_link,
        ]);
    }

    /** Full URL embedded in the physical QR code. */
    public function getAttendanceUrlAttribute(): string
    {
        return route('events.attend', ['id' => $this->id, 'token' => $this->attendance_token]);
    }

    /** Resolved image URL with a placeholder fallback. */
    public function getFeaturedImageUrlAttribute(): string
    {
        return $this->featured_image_path
            ? asset('storage/' . $this->featured_image_path)
            : 'https://placehold.co/800x450/e2e8f0/94a3b8?text=' . urlencode($this->title);
    }

    // ─ Relationships ─────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_rsvps')
                    ->withPivot(['status', 'attended_at'])
                    ->withTimestamps();
    }
}
