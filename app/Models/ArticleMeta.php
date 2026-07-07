<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleMeta extends Model
{
    protected $guarded = [];

    protected $table = 'article_meta';

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
