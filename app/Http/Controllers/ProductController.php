<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category', 'organization');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $sort = $request->input('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::all();

        return Inertia::render('Ecommerce/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'sort']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Product::class);
        $categories = Category::all();
        return Inertia::render('Ecommerce/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'postage_cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:2048',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required|string|max:255',
            'variations.*.type' => 'required|in:select,color',
            'variations.*.required' => 'boolean',
            'variations.*.options' => 'nullable|array',
            'variations.*.options.*.name' => 'required|string|max:255',
            'variations.*.options.*.price_adjustment' => 'nullable|numeric',
            'variations.*.options.*.stock' => 'nullable|integer|min:0',
            'variations.*.options.*.hex_color' => 'nullable|string|max:7',
        ];

        $request->validate($rules);

        $data = $request->only('name', 'description', 'price', 'postage_cost', 'stock', 'category_id');
        $data['organisasi_id'] = Auth::user()->organisasi_id ?? null;
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $extraImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                if ($img) {
                    $extraImages[] = $img->store('products', 'public');
                }
            }
        }
        $data['images'] = $extraImages;

        $product = Product::create($data);

        $variations = $this->parseVariations($request);
        if (!empty($variations)) {
            $this->syncVariations($product, $variations);
        }

        return redirect()->route('products.index')->with('success', 'Produk berjaya ditambah!');
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'organization',
            'variations.options',
        ]);

        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', true)
            ->limit(4)
            ->get();

        return Inertia::render('Ecommerce/Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);
        $categories = Category::all();
        $product->load('category', 'variations.options');

        return Inertia::render('Ecommerce/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'postage_cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:2048',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required|string|max:255',
            'variations.*.type' => 'required|in:select,color',
            'variations.*.required' => 'boolean',
            'variations.*.options' => 'nullable|array',
            'variations.*.options.*.name' => 'required|string|max:255',
            'variations.*.options.*.price_adjustment' => 'nullable|numeric',
            'variations.*.options.*.stock' => 'nullable|integer|min:0',
            'variations.*.options.*.hex_color' => 'nullable|string|max:7',
        ];

        $request->validate($rules);

        $data = $request->only('name', 'description', 'price', 'postage_cost', 'stock', 'category_id');
        $data['status'] = $request->has('status');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->hasFile('images')) {
            $extraImages = [];
            foreach ($request->file('images') as $img) {
                if ($img) {
                    $extraImages[] = $img->store('products', 'public');
                }
            }
            $data['images'] = $extraImages;
        }

        $product->update($data);

        $variations = $this->parseVariations($request);
        $this->syncVariations($product, $variations);

        return redirect()->route('products.index')->with('success', 'Produk berjaya dikemaskini!');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Produk berjaya dipadam!');
    }

    private function parseVariations(Request $request): array
    {
        $variations = $request->input('variations');
        if (is_string($variations)) {
            return json_decode($variations, true) ?? [];
        }
        return $variations ?? [];
    }

    private function syncVariations(Product $product, array $variations): void
    {
        $existingIds = $product->variations()->pluck('id');

        $submittedIds = [];
        foreach ($variations as $vIndex => $variation) {
            $varData = [
                'name' => $variation['name'],
                'type' => $variation['type'] ?? 'select',
                'required' => $variation['required'] ?? true,
                'sort_order' => $vIndex,
            ];

            if (!empty($variation['id'])) {
                $var = $product->variations()->where('id', $variation['id'])->first();
                if ($var) {
                    $var->update($varData);
                    $submittedIds[] = $var->id;
                } else {
                    $var = $product->variations()->create($varData);
                    $submittedIds[] = $var->id;
                }
            } else {
                $var = $product->variations()->create($varData);
                $submittedIds[] = $var->id;
            }

            $existingOptionIds = $var->options()->pluck('id');
            $submittedOptionIds = [];

            foreach (($variation['options'] ?? []) as $oIndex => $option) {
                $optData = [
                    'name' => $option['name'],
                    'price_adjustment' => !empty($option['price_adjustment']) ? $option['price_adjustment'] : null,
                    'stock' => !empty($option['stock']) ? $option['stock'] : null,
                    'hex_color' => $option['hex_color'] ?? null,
                    'sort_order' => $oIndex,
                ];

                if (!empty($option['id'])) {
                    $opt = $var->options()->where('id', $option['id'])->first();
                    if ($opt) {
                        $opt->update($optData);
                        $submittedOptionIds[] = $opt->id;
                    } else {
                        $opt = $var->options()->create($optData);
                        $submittedOptionIds[] = $opt->id;
                    }
                } else {
                    $opt = $var->options()->create($optData);
                    $submittedOptionIds[] = $opt->id;
                }
            }

            $var->options()->whereNotIn('id', $submittedOptionIds)->delete();
        }

        $product->variations()->whereNotIn('id', $submittedIds)->each(function ($var) {
            $var->options()->delete();
            $var->delete();
        });
    }
}
