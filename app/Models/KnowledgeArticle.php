<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeArticle extends Model
{
    const CATEGORIES = ['Yuran', 'Polisi', 'Event', 'Pendaftaran', 'Teknikal', 'Lain-lain'];

    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'category',
        'document_path',
        'document_content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
