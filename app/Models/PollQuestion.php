<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'question_text',
        'type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class, 'poll_question_id')->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'poll_question_id');
    }
}
