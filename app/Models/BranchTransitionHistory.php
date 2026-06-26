<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTransitionHistory extends Model
{
    protected $fillable = [
        'user_id',
        'from_branch_id',
        'to_branch_id',
        'changed_by',
        'change_type',
        'request_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BranchChangeRequest::class, 'request_id');
    }
}
