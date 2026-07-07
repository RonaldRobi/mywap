<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleMedia extends Model
{
    protected $guarded = [];

    protected $table = 'article_media';

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
