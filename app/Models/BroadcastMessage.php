<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'target_organization_id',
        'title',
        'content',
        'target_criteria',
        'recipient_ids',
        'notification_channels',
        'email_use_template',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipient_ids' => 'array',
            'notification_channels' => 'array',
            'email_use_template' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'target_organization_id');
    }
}
