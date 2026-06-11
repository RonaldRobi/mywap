<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $industry = trim((string) $request->query('industry', ''));

        $query = User::query()
            ->with('organization')
            ->where('is_public_in_directory', true)
            ->whereNotNull('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('expertise', 'like', "%{$search}%");
            });
        }

        if ($industry !== '') {
            $query->where('industry', $industry);
        }

        $users = $query->orderBy('name')
            ->paginate(16)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'industry' => $user->industry,
                'expertise' => $user->expertise,
                'linkedin_url' => $user->linkedin_url,
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                ],
            ]);

        $industries = User::query()
            ->where('is_public_in_directory', true)
            ->whereNotNull('industry')
            ->select('industry')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->values();

        return Inertia::render('Directory/Index', [
            'users' => $users,
            'industries' => $industries,
            'filters' => [
                'search' => $search,
                'industry' => $industry,
            ],
        ]);
    }
}
