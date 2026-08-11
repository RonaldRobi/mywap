<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'member_price',
        'postage_cost',
        'stock',
        'category_id',
        'organisasi_id',
        'image',
        'images',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'status' => 'boolean',
            'price' => 'decimal:2',
            'member_price' => 'decimal:2',
            'postage_cost' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    /**
     * Only products that are published to the mall.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Price a given user pays, honouring the member price when set.
     */
    public function priceFor(?User $user = null): float
    {
        if ($user && $this->member_price !== null) {
            return (float) $this->member_price;
        }

        return (float) $this->price;
    }

    /**
     * Main image plus gallery images, de-duplicated.
     *
     * @return array<int, string>
     */
    public function getGalleryAttribute(): array
    {
        return array_values(array_unique(array_filter(
            array_merge([$this->image], $this->images ?? [])
        )));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organisasi_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->orderBy('sort_order');
    }
}
