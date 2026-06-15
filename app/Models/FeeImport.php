<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'total_rows',
        'success_count',
        'skip_count',
        'csv_file',
        'proof_file',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
