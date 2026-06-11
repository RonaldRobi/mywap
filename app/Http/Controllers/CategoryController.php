<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return Inertia::render('Ecommerce/Categories/Index', [
            'categories' => $categories,
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
        Category::create($request->only('name', 'description'));
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
