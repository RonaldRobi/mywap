<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pendukung
 *
 * Orang awam (bukan ahli berdaftar) yang disimpan untuk hebahan dan jemputan
 * mendaftar. Rekod boleh datang daripada program, import CSV, atau tambah
 * manual. Tiada akaun dalam sistem.
 */
class Supporter extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_PROGRAM = 'program';

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'ic_number',
        'source',
        'notes',
        'is_active',
        'converted_user_id',
        'converted_at',
        'last_invited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'converted_at' => 'datetime',
            'last_invited_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || trim($value) === ''
                ? null
                : mb_strtolower(trim($value)),
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'phone' => $value,
                'phone_normalized' => Registration::normalizePhone($value),
            ],
        );
    }

    protected function icNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'ic_number' => $value,
                'ic_normalized' => Registration::normalizeIc($value),
            ],
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_supporter')
            ->withPivot('source')
            ->withTimestamps();
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }
}
