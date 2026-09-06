<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastMessage extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    /** Label sumber untuk siaran superadmin tanpa pilih organisasi (siaran platform). */
    public const PLATFORM_SENDER_LABEL = 'MyWAP';

    protected $fillable = [
        'organization_id',
        'sender_label',
        'target_organization_id',
        'branch_id',
        'title',
        'content',
        'target_criteria',
        'recipient_ids',
        'notification_channels',
        'email_use_template',
        'sent_at',
        'status',
        'recipient_count',
        'success_count',
        'failed_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'recipient_ids' => 'array',
            'notification_channels' => 'array',
            'email_use_template' => 'boolean',
            'recipient_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'target_organization_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class)->latest('id');
    }
}
