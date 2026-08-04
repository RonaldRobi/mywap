<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'user_id',
        'total_donated',
        'donation_count',
        'last_donated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_donated'   => 'decimal:2',
            'last_donated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(InfaqDonation::class);
    }
}
