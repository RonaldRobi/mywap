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
        'price_tiers',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_public' => 'boolean',
            'recipient_emails' => 'array',
            'payment_required' => 'boolean',
            'price' => 'decimal:2',
            'price_tiers' => 'array',
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

    /**
     * Pilihan Negeri → Cawangan untuk soalan jenis "branch".
     * Dijana daripada cawangan aktif organisasi borang (dikumpul ikut negeri).
     */
    public function branchOptions(): array
    {
        $orgId = $this->organization_id;

        // Jika borang tiada org, guna org pemilik event (borang pendaftaran event).
        if (! $orgId && $this->event) {
            $orgId = $this->event->organization_id;
        }

        if (! $orgId) {
            return [];
        }

        return Branch::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('state')
            ->orderBy('name')
            ->get(['id', 'name', 'state'])
            ->groupBy('state')
            ->map(fn ($branches) => [
                'state' => $branches->first()->state,
                'branches' => $branches
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
                    ->values(),
            ])
            ->values()
            ->all();
    }

    public function getPublicUrlAttribute(): string
    {
        return route('forms.public', ['token' => $this->share_token]);
    }

    public function getShareUrlAttribute(): string
    {
        return route('share.form', $this, true);
    }

    // ─── Harga Tier (cth. Pelajar / Orang Awam) ─────────────────────────────

    /**
     * Senarai tier harga (dinormalkan). Setiap item: { label, price,
     * is_default, requires_document, description? }.
     */
    public function tiers(): array
    {
        $raw = $this->price_tiers ?? [];

        return collect($raw)
            ->filter(fn ($t) => is_array($t) && isset($t['label'], $t['price']))
            ->map(function ($t) {
                return [
                    'label' => (string) $t['label'],
                    'price' => (float) $t['price'],
                    'is_default' => (bool) ($t['is_default'] ?? false),
                    'requires_document' => (bool) ($t['requires_document'] ?? false),
                    'description' => $t['description'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function hasTiers(): bool
    {
        return $this->tiers() !== [];
    }

    public function defaultTier(): ?array
    {
        foreach ($this->tiers() as $tier) {
            if ($tier['is_default']) {
                return $tier;
            }
        }

        return $this->tiers()[0] ?? null;
    }

    public function tierByLabel(?string $label): ?array
    {
        if (! $label) {
            return null;
        }

        foreach ($this->tiers() as $tier) {
            if ($tier['label'] === $label) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Harga efektif untuk satu tier label, atau harga default fallback.
     */
    public function priceForTier(?string $label): ?float
    {
        if ($label) {
            $tier = $this->tierByLabel($label);
            if ($tier) {
                return $tier['price'];
            }
        }

        $default = $this->defaultTier();

        return $default ? $default['price'] : (float) $this->price;
    }

    public function tierRequiresDocument(?string $label): bool
    {
        $tier = $this->tierByLabel($label);

        return $tier ? (bool) ($tier['requires_document'] ?? false) : false;
    }
}
