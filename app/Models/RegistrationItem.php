<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RegistrationItem
 *
 * Snapshot of one add-on line purchased for a registration. Stored separately
 * from FormItem so historical records survive later edits/deletions of items.
 */
class RegistrationItem extends Model
{
    protected $fillable = [
        'registration_id',
        'form_item_id',
        'form_item_variant_id',
        'name',
        'variant_name',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FormItem::class, 'form_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(FormItemVariant::class, 'form_item_variant_id');
    }
}
