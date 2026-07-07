<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function comments()
    {
        return $this->hasMany(ArticleComment::class);
    }

    public function reactions()
    {
        return $this->hasMany(ArticleReaction::class);
    }

    public function media()
    {
        return $this->hasMany(ArticleMedia::class)->orderBy('order');
    }

    public function categories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'article_article_category');
    }

    public function tags()
    {
        return $this->belongsToMany(ArticleTag::class, 'article_article_tag');
    }

    public function meta()
    {
        return $this->hasMany(ArticleMeta::class);
    }
}
