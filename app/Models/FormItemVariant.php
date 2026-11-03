<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FormItemVariant
 *
 * A selectable variant of a FormItem (e.g. a jersey size). A NULL price means
 * the variant inherits the parent item price; a stock of NULL means unlimited.
 */
class FormItemVariant extends Model
{
    protected $fillable = [
        'form_item_id',
        'name',
        'price',
        'stock',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FormItem::class, 'form_item_id');
    }
}
