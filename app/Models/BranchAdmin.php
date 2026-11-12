<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchAdmin extends Model
{
    use HasFactory;

    public const ROLE_MANAGER = 'manager';

    public const ROLE_SETIAUSAHA = 'setiausaha';

    public const MAX_SETIAUSAHA = 3;

    protected $fillable = [
        'branch_id',
        'user_id',
        'created_by',
        'role',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
