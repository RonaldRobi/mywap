<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ArticleTag extends Model
{
    protected $guarded = [];

    protected $table = 'article_tags';

    protected static function booted()
    {
        static::creating(function ($tag) {
            if (!$tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_article_tag');
    }
}
