<?php

namespace App\Models;

use App\Enums\FeeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'year',
        'amount',
        'status',
        'paid_at',
        'payment_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'status' => FeeStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
