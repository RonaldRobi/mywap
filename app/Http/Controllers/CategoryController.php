<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request)
    {
        return Inertia::render('Ecommerce/Categories/Index', [
            'categories' => $this->products->categories($request->user()),
            'isSuperadmin' => $request->user()->isSuperadmin(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Category::class);

        return Inertia::render('Ecommerce/Categories/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Category::class);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();

        Category::create([
            ...$request->only('name', 'description'),
            // Superadmin creates shared/global categories; org admins create
            // categories that belong to their own organisation.
            'organization_id' => $user->isSuperadmin() ? null : $user->current_organization_id,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berjaya ditambah!');
    }

    public function edit(Category $category)
    {
        $this->authorize('update', $category);

        return Inertia::render('Ecommerce/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $category->update($request->only('name', 'description'));

        return redirect()->route('categories.index')->with('success', 'Kategori berjaya dikemaskini!');
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori berjaya dipadam!');
    }
}
