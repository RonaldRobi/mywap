<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendukung
 *
 * Emel orang awam (bukan ahli berdaftar) yang disimpan khas untuk tujuan
 * hebahan. Tidak mempunyai akaun dalam sistem; digunakan oleh siaran emel.
 */
class Supporter extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'source',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
