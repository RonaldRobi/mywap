<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EventPoster extends Model
{
    protected $fillable = ['event_id', 'path', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
