<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $emailTemplate->update($data);

        return back()->with('success', 'Template emel berjaya dikemas kini.');
    }
}
