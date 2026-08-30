<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = EmailTemplate::orderBy('key')->get();

        return Inertia::render('Admin/EmailTemplates', [
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $updateData = $data;

        // Upload header image (logo) for this template.
        if ($request->hasFile('header_image')) {
            $request->validate([
                'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ]);

            if ($emailTemplate->header_image_path) {
                $this->deleteHeaderImage($emailTemplate->header_image_path);
            }

            $path = $request->file('header_image')->store('email-templates', 'public');
            $updateData['header_image_path'] = '/storage/'.ltrim($path, '/');
        }

        // Remove header image.
        if ($request->boolean('remove_header_image') && $emailTemplate->header_image_path) {
            $this->deleteHeaderImage($emailTemplate->header_image_path);
            $updateData['header_image_path'] = null;
        }

        $emailTemplate->update($updateData);

        return back()->with('success', 'Template emel berjaya dikemas kini.');
    }

    private function deleteHeaderImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relative = ltrim(str_replace('/storage/', '', parse_url($path, PHP_URL_PATH) ?? ''), '/');
        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
