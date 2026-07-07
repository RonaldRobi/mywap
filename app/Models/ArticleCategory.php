<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ArticleCategory extends Model
{
    protected $guarded = [];

    protected $table = 'article_categories';

    protected static function booted()
    {
        static::creating(function ($category) {
            if (!$category->slug) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_article_category');
    }
}
