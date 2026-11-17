<?php

namespace App\Services;

use App\Models\MediaStatement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class MediaStatementService
{
    public function serializeSummary(MediaStatement $statement): array
    {
        return [
            'id' => $statement->id,
            'title' => $statement->title,
            'featured_image' => $statement->featured_image_path ? asset($statement->featured_image_path) : null,
            'published_date' => ($statement->published_at ?? $statement->created_at)?->format('d/m/Y'),
            'public_url' => route('media-statements.show', $statement),
            'detail_url' => route('api.v1.media-statements.show', $statement),
            'pdf_url' => $statement->pdf_path ? asset($statement->pdf_path) : null,
            'is_featured' => $statement->is_featured,
        ];
    }

    public function list(Request $request, ?User $user = null): LengthAwarePaginator
    {
        return $this->visibleQuery($user)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (MediaStatement $statement) => $this->serializeSummary($statement));
    }

    public function detail(MediaStatement $statement): array
    {
        $statement->loadMissing(['organization:id,name,slug', 'attachments:id,media_statement_id,path,type']);

        return [
            ...$this->serializeSummary($statement),
            'content' => $statement->content,
            'organization' => $statement->organization?->only(['id', 'name', 'slug']),
            'attachments' => $statement->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'type' => $attachment->type,
                'url' => asset($attachment->path),
            ])->values(),
        ];
    }

    public function visibleQuery(?User $user = null)
    {
        return MediaStatement::with('organization:id,name,slug')
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('organization_id')->when($user?->current_organization_id, fn ($q, $orgId) => $q->orWhere('organization_id', $orgId)))
            ->latest('published_at');
    }
}
