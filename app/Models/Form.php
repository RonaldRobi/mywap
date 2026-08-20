<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'event_id',
        'title',
        'slug',
        'description',
        'price',
        'payment_required',
        'terms',
        'is_active',
        'allow_public',
        'share_token',
        'header_image_path',
        'recipient_emails',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_public' => 'boolean',
            'recipient_emails' => 'array',
            'payment_required' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Form $form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->title).'-'.Str::lower(Str::random(6));
            }
            if (empty($form->share_token)) {
                $form->share_token = Str::random(32);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('forms.public', ['token' => $this->share_token]);
    }

    public function getShareUrlAttribute(): string
    {
        return route('share.form', $this, true);
    }
}
