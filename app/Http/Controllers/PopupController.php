<?php

namespace App\Http\Controllers;

use App\Models\Popup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PopupController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $popups = Popup::query()
            ->with('organization:id,name,slug')
            ->when($user->hasRole('Admin') && !$user->hasRole('Superadmin'), function ($query) use ($user) {
                $query->where('organization_id', $user->current_organization_id);
            })
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Popup $popup) => [
                'id' => $popup->id,
                'title' => $popup->title,
                'content' => $popup->content,
                'image_path' => $popup->image_path,
                'button_text' => $popup->button_text,
                'button_url' => $popup->button_url,
                'button_text_2' => $popup->button_text_2,
                'button_url_2' => $popup->button_url_2,
                'popup_size' => $popup->popup_size,
                'is_active' => $popup->is_active,
                'display_order' => $popup->display_order,
                'start_at' => $popup->start_at?->toISOString(),
                'end_at' => $popup->end_at?->toISOString(),
                'organization_id' => $popup->organization_id,
                'organization_name' => $popup->organization?->name ?? 'Global',
            ]);

        return Inertia::render('Admin/PopupManage', [
            'popups' => $popups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'button_text_2' => ['nullable', 'string', 'max:255'],
            'button_url_2' => ['nullable', 'string', 'max:255'],
            'popup_size' => ['nullable', 'string', 'in:sm,md,lg'],
            'is_active' => ['nullable', 'in:0,1,true,false'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $storedPath = $request->file('image')->store('popups', 'public');
            $imagePath = '/storage/' . ltrim($storedPath, '/');
        }

        Popup::create([
            'organization_id' => $request->user()->current_organization_id,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'image_path' => $imagePath,
            'button_text' => $data['button_text'] ?? null,
            'button_url' => $data['button_url'] ?? null,
            'button_text_2' => $data['button_text_2'] ?? null,
            'button_url_2' => $data['button_url_2'] ?? null,
            'popup_size' => $data['popup_size'] ?? 'md',
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'display_order' => (int) ($data['display_order'] ?? 1),
            'start_at' => !empty($data['start_at']) ? $data['start_at'] : null,
            'end_at' => !empty($data['end_at']) ? $data['end_at'] : null,
        ]);

        return back()->with('success', 'Popup berjaya dibuat.');
    }

    public function update(Request $request, Popup $popup): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'button_text_2' => ['nullable', 'string', 'max:255'],
            'button_url_2' => ['nullable', 'string', 'max:255'],
            'popup_size' => ['nullable', 'string', 'in:sm,md,lg'],
            'is_active' => ['nullable', 'in:0,1,true,false'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $imagePath = $popup->image_path;

        if ($request->hasFile('image')) {
            if ($popup->image_path) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url($popup->image_path, PHP_URL_PATH) ?? ''), '/');
                if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $newPath = $request->file('image')->store('popups', 'public');
            $imagePath = '/storage/' . ltrim($newPath, '/');
        }

        $popup->update([
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'image_path' => $imagePath,
            'button_text' => $data['button_text'] ?? null,
            'button_url' => $data['button_url'] ?? null,
            'button_text_2' => $data['button_text_2'] ?? null,
            'button_url_2' => $data['button_url_2'] ?? null,
            'popup_size' => $data['popup_size'] ?? 'md',
            'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'display_order' => (int) ($data['display_order'] ?? 1),
            'start_at' => !empty($data['start_at']) ? $data['start_at'] : null,
            'end_at' => !empty($data['end_at']) ? $data['end_at'] : null,
        ]);

        return back()->with('success', 'Popup berjaya dikemas kini.');
    }

    public function destroy(Popup $popup): RedirectResponse
    {
        if ($popup->image_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($popup->image_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $popup->delete();

        return back()->with('success', 'Popup berjaya dipadam.');
    }

}
