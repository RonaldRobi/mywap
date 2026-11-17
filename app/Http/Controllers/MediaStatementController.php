<?php

namespace App\Http\Controllers;

use App\Models\MediaStatement;
use App\Models\Organization;
use App\Services\MediaStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MediaStatementController extends Controller
{
    public function __construct(private readonly MediaStatementService $statements) {}

    public function index(Request $request)
    {
        $statements = $this->statements->visibleQuery($request->user())->limit(60)->get()
            ->map(fn (MediaStatement $statement) => $this->statements->serializeSummary($statement));

        return Inertia::render('MediaStatement/Index', ['statements' => $statements]);
    }

    public function show(Request $request, MediaStatement $mediaStatement)
    {
        abort_unless($this->statements->visibleQuery($request->user())->whereKey($mediaStatement)->exists(), 404);

        return Inertia::render('MediaStatement/Show', ['statement' => $this->statements->detail($mediaStatement)]);
    }

    public function adminIndex(Request $request)
    {
        $statements = MediaStatement::with(['author', 'organization'])
            ->when(! $request->user()->isSuperadmin(), fn ($q) => $q->where('organization_id', $request->user()->current_organization_id))
            ->latest('created_at')->paginate(15);

        return Inertia::render('Admin/MediaStatements/Index', compact('statements'));
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/MediaStatements/Create', ['organizations' => $this->manageableOrganizations($request->user())]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $user = $request->user();
        $data['organization_id'] = $user->isSuperadmin() ? ($data['organization_id'] ?? null) : $user->current_organization_id;
        $data['author_id'] = $user->id;
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['published_at'] = $data['is_published'] ? now() : null;
        $this->storeFiles($request, $data);

        $statement = MediaStatement::create($data);
        $this->storeAttachments($request, $statement);

        return redirect()->route('admin.media-statements.edit', $statement)->with('success', 'Kenyataan media berjaya dicipta.');
    }

    public function edit(Request $request, MediaStatement $mediaStatement)
    {
        $this->authorizeAccess($request->user(), $mediaStatement);
        $mediaStatement->load('attachments');

        return Inertia::render('Admin/MediaStatements/Edit', [
            'statement' => $mediaStatement,
            'organizations' => $this->manageableOrganizations($request->user()),
        ]);
    }

    public function update(Request $request, MediaStatement $mediaStatement)
    {
        $this->authorizeAccess($request->user(), $mediaStatement);
        $data = $this->validateData($request);
        $data['organization_id'] = $request->user()->isSuperadmin() ? ($data['organization_id'] ?? null) : $request->user()->current_organization_id;
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['published_at'] = $data['is_published'] ? ($mediaStatement->published_at ?? now()) : null;
        $this->storeFiles($request, $data, $mediaStatement);

        $mediaStatement->update($data);
        $this->storeAttachments($request, $mediaStatement);

        return back()->with('success', 'Kenyataan media berjaya dikemaskini.');
    }

    public function destroy(Request $request, MediaStatement $mediaStatement)
    {
        $this->authorizeAccess($request->user(), $mediaStatement);
        $mediaStatement->load('attachments');
        foreach (array_filter([$mediaStatement->featured_image_path, $mediaStatement->pdf_path, ...$mediaStatement->attachments->pluck('path')->all()]) as $path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $path));
        }
        $mediaStatement->delete();

        return redirect()->route('admin.media-statements.index')->with('success', 'Kenyataan media berjaya dipadam.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'featured_image' => ['nullable', 'image', 'max:4096'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['image', 'max:4096'],
        ]);
    }

    private function storeFiles(Request $request, array &$data, ?MediaStatement $statement = null): void
    {
        if ($request->hasFile('featured_image')) {
            if ($statement?->featured_image_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $statement->featured_image_path));
            }
            $data['featured_image_path'] = '/storage/'.$request->file('featured_image')->store('media-statements', 'public');
        }
        if ($request->hasFile('pdf')) {
            if ($statement?->pdf_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $statement->pdf_path));
            }
            $data['pdf_path'] = '/storage/'.$request->file('pdf')->store('media-statements/pdf', 'public');
        }
        unset($data['featured_image'], $data['pdf'], $data['attachments']);
    }

    private function storeAttachments(Request $request, MediaStatement $statement): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $statement->attachments()->create([
                'path' => '/storage/'.$file->store('media-statements/attachments', 'public'),
                'type' => 'image',
            ]);
        }
    }

    private function authorizeAccess($user, MediaStatement $statement): void
    {
        abort_if(! $user->isSuperadmin() && (int) $statement->organization_id !== (int) $user->current_organization_id, 403);
    }

    private function manageableOrganizations($user)
    {
        return $user->isSuperadmin()
            ? Organization::orderBy('name')->get()
            : Organization::whereKey($user->current_organization_id)->get();
    }
}
