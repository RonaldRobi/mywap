<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log for each CSV/XLSX registration upload. The registrations table is
 * the source of truth; this model records what a batch changed so admins can
 * review repeated uploads without guessing.
 */
class RegistrationImport extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'filename', 'headers', 'total_rows',
        'created_count', 'updated_count', 'skipped_count', 'errors',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'errors' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
