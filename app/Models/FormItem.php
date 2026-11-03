<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FormItem
 *
 * An optional add-on / merchandise item attached to a registration form
 * (e.g. "Jersi ABIM Run"). Participants may purchase zero or more of these
 * alongside their registration. See FormItemVariant for sizes/variants.
 *
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property float $price
 * @property int|null $stock
 * @property bool $is_active
 */
class FormItem extends Model
{
    protected $fillable = [
        'form_id',
        'name',
        'description',
        'price',
        'image',
        'stock',
        'is_active',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(FormItemVariant::class)->orderBy('sort_order')->orderBy('id');
    }
}
