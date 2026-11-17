<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaStatementAttachment extends Model
{
    protected $guarded = [];

    public function mediaStatement()
    {
        return $this->belongsTo(MediaStatement::class);
    }
}
