<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\Supporter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengurusan "Pendukung" — senarai emel orang awam (bukan ahli) untuk hebahan.
 */
class SupporterController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $search = trim((string) $request->input('search'));

        $supporters = Supporter::query()
            ->with('organization:id,name')
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Supporter $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'phone' => $s->phone,
                'source' => $s->source,
                'notes' => $s->notes,
                'is_active' => $s->is_active,
                'organization_name' => $s->organization?->name,
                'created_at' => $s->created_at?->format('d M Y'),
            ]);

        $organizations = $isSuperadmin
            ? Organization::query()->orderBy('min_age')->get(['id', 'name'])
            : collect([['id' => $user->organization?->id, 'name' => $user->organization?->name]]);

        return Inertia::render('Admin/Supporters', [
            'supporters' => $supporters,
            'isSuperadmin' => $isSuperadmin,
            'organizations' => $organizations,
            'defaultOrganizationId' => $user->current_organization_id,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $orgId = $isSuperadmin
            ? ($data['organization_id'] ?? null)
            : $user->current_organization_id;

        $email = strtolower(trim($data['email']));

        $duplicate = Supporter::query()
            ->where('email', $email)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId), fn ($q) => $q->whereNull('organization_id'))
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['email' => 'Emel ini sudah ada dalam senarai Pendukung.']);
        }

        Supporter::create([
            'organization_id' => $orgId,
            'name' => $data['name'] ?? null,
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'source' => 'manual',
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Pendukung berjaya ditambah.');
    }

    public function import(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $orgId = $isSuperadmin
            ? $request->input('organization_id')
            : $user->current_organization_id;

        // Emel ahli sedia ada (semua organisasi) tidak perlu dijadikan Pendukung.
        $memberEmails = User::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip()
            ->all();

        $existingEmails = Supporter::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId), fn ($q) => $q->whereNull('organization_id'))
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip()
            ->all();

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'Fail tidak dapat dibaca.']);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        $imported = 0;
        $skipMember = 0;
        $skipExisting = 0;
        $invalid = 0;

        $emailIdx = 0;
        $nameIdx = null;
        $phoneIdx = null;
        $start = 0;

        // Kesan baris header: jika baris pertama tiada emel, anggap ia header.
        if ($rows !== []) {
            $first = $rows[0];
            $hasEmailInFirst = collect($first)->contains(fn ($c) => is_string($c) && str_contains($c, '@'));

            if (! $hasEmailInFirst) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $first);

                $emailIdx = array_search('email', $header, true);
                if ($emailIdx === false) {
                    $emailIdx = array_search('emel', $header, true);
                }
                if ($emailIdx === false) {
                    $emailIdx = 0;
                }

                $nameIdx = array_search('name', $header, true);
                if ($nameIdx === false) {
                    $nameIdx = array_search('nama', $header, true);
                }

                $phoneIdx = array_search('phone', $header, true);
                if ($phoneIdx === false) {
                    $phoneIdx = array_search('telefon', $header, true);
                }

                $start = 1;
            }
        }

        $nameIdx = $nameIdx === false ? null : $nameIdx;
        $phoneIdx = $phoneIdx === false ? null : $phoneIdx;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $email = strtolower(trim((string) ($row[$emailIdx] ?? '')));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;
                continue;
            }

            if (isset($memberEmails[$email])) {
                $skipMember++;
                continue;
            }

            if (isset($existingEmails[$email])) {
                $skipExisting++;
                continue;
            }

            Supporter::create([
                'organization_id' => $orgId,
                'name' => $nameIdx !== null ? (trim((string) ($row[$nameIdx] ?? '')) ?: null) : null,
                'email' => $email,
                'phone' => $phoneIdx !== null ? (trim((string) ($row[$phoneIdx] ?? '')) ?: null) : null,
                'source' => 'import',
                'is_active' => true,
            ]);

            $existingEmails[$email] = true;
            $imported++;
        }

        return back()->with(
            'success',
            "Import selesai: {$imported} ditambah, {$skipMember} dilangkau (ahli), {$skipExisting} dilangkau (sudah ada), {$invalid} tidak sah."
        );
    }

    public function destroy(Request $request, Supporter $supporter): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Superadmin')
            && (int) $supporter->organization_id !== (int) $user->current_organization_id) {
            abort(403);
        }

        $supporter->delete();

        return back()->with('success', 'Pendukung berjaya dibuang.');
    }
}
