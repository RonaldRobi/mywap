<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Manual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengurusan Manual Pengguna (Superadmin).
 *
 * Muat naik PDF manual dan tetapkan sasaran peranan (`audience`) supaya
 * ahli hanya nampak manual ahli dan admin hanya nampak manual admin.
 */
class ManualController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Manual::query()->orderBy('audience')->orderBy('sort_order')->latest('id');

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($audience = $request->input('audience')) {
            $query->where('audience', $audience);
        }

        return Inertia::render('Admin/ManualManage', [
            'manuals' => $query->paginate(15)->withQueryString(),
            'audiences' => Manual::AUDIENCES,
            'filters' => $request->only(['search', 'audience']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'in:'.implode(',', Manual::AUDIENCES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:30720'],
        ]);

        $storedPath = $request->file('document')->store('manuals', 'public');

        Manual::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'audience' => $data['audience'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'file_path' => '/storage/'.ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Manual berjaya dimuat naik.');
    }

    public function update(Request $request, Manual $manual): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'in:'.implode(',', Manual::AUDIENCES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:30720'],
        ]);

        $manual->title = $data['title'];
        $manual->description = $data['description'] ?? null;
        $manual->audience = $data['audience'];
        $manual->sort_order = $data['sort_order'] ?? 0;
        $manual->is_active = $request->has('is_active') ? (bool) $data['is_active'] : $manual->is_active;

        if ($request->hasFile('document')) {
            $this->deleteStoredFile($manual->file_path);
            $storedPath = $request->file('document')->store('manuals', 'public');
            $manual->file_path = '/storage/'.ltrim($storedPath, '/');
        }

        $manual->save();

        return back()->with('success', 'Manual berjaya dikemas kini.');
    }

    public function destroy(Manual $manual): RedirectResponse
    {
        $this->deleteStoredFile($manual->file_path);
        $manual->delete();

        return back()->with('success', 'Manual berjaya dipadam.');
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relative = ltrim(str_replace('/storage/', '', parse_url($path, PHP_URL_PATH) ?? ''), '/');

        if ($relative && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
