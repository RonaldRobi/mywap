<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAnswer extends Model
{
    protected $fillable = [
        'form_response_id',
        'form_question_id',
        'value',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class);
    }
}
