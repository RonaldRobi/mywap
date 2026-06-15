<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_response_id',
        'poll_question_id',
        'poll_option_id',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(PollResponse::class, 'poll_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PollQuestion::class, 'poll_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }
}
