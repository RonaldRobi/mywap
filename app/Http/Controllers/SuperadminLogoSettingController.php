<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminLogoSettingController extends Controller
{
    public function index(): Response
    {
        $hasAppSettingsTable = Schema::hasTable('app_settings');
        $hasOrganizationLogoColumn = Schema::hasColumn('organizations', 'logo_path');

        $setting = $hasAppSettingsTable
            ? AppSetting::singleton()
            : null;

        return Inertia::render('Superadmin/LogoSettings', [
            'systemLogoPath' => $setting?->system_logo_path,
            'canManageSystemLogo' => $hasAppSettingsTable,
            'canManageOrganizationLogo' => $hasOrganizationLogoColumn,
            'organizations' => Organization::query()
                ->orderBy('min_age')
                ->get($hasOrganizationLogoColumn ? ['id', 'name', 'slug', 'logo_path'] : ['id', 'name', 'slug'])
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'logo_path' => $hasOrganizationLogoColumn ? $organization->logo_path : null,
                ]),
        ]);
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu untuk tetapan logo sistem.');
        }

        $data = $request->validate([
            'system_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $setting = AppSetting::singleton();

        if ($setting->system_logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $setting->system_logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['system_logo']->store('logos/system', 'public');
        $setting->update([
            'system_logo_path' => '/storage/' . ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Logo sistem berjaya dikemas kini.');
    }

    public function updateOrganization(Request $request, Organization $organization): RedirectResponse
    {
        if (! Schema::hasColumn('organizations', 'logo_path')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu untuk logo organisasi.');
        }

        $data = $request->validate([
            'organization_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        if ($organization->logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $organization->logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['organization_logo']->store('logos/organizations', 'public');

        $organization->update([
            'logo_path' => '/storage/' . ltrim($storedPath, '/'),
        ]);

        return back()->with('success', "Logo {$organization->name} berjaya dikemas kini.");
    }
}
