<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request)
    {
        $isMall = $request->routeIs('mall.*');

        return Inertia::render('Ecommerce/Products/Index', [
            'products' => $this->products->list($request, $request->user(), $isMall),
            'categories' => $this->products->categoryOptions(),
            'filters' => $request->only(['search', 'category_id', 'sort', 'status']),
            'isMall' => $isMall,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Ecommerce/Products/Create', [
            'categories' => Category::orderBy('name')->get(),
            ...$this->organizationProps($request),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        // The Inertia form posts as multipart/form-data, so `variations` arrives
        // as a JSON string. Decode it back into an array *before* validating,
        // otherwise the `array` rule rejects every submission.
        $this->normalizeVariations($request);

        $validated = $request->validate(
            $this->productRules($request),
            [],
            $this->validationAttributes()
        );

        $data = $this->productPayload($request, $validated);
        $data['organisasi_id'] = $this->resolveOrganisationId($request);
        $data['images'] = $this->storeExtraImages($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        $this->syncVariations($product, $validated['variations'] ?? []);

        return redirect()->route('products.index')->with('success', 'Produk berjaya ditambah!');
    }

    public function show(Request $request, Product $product)
    {
        // Drafts are previewable by admins only; buyers get a 404 rather than
        // a page for something that is not on sale.
        $isAdmin = $request->user() && $request->user()->hasRole(['Superadmin', 'Admin']);
        abort_if(! $product->status && ! $isAdmin, 404);

        $detail = $this->products->showDetail($product, $request->user());

        return Inertia::render('Ecommerce/Products/Show', [
            'product' => $detail['product'],
            'relatedProducts' => $detail['relatedProducts'],
            'isMall' => $request->routeIs('mall.*'),
        ]);
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $product->load('category', 'variations.options');

        return Inertia::render('Ecommerce/Products/Edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            ...$this->organizationProps($request),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $this->normalizeVariations($request);

        $validated = $request->validate(
            $this->productRules($request),
            [],
            $this->validationAttributes()
        );

        $data = $this->productPayload($request, $validated);

        // Only a Superadmin may move a product between organisations; an org
        // Admin's product always stays with their own org, so we leave
        // organisasi_id untouched for them.
        if ($request->user()->hasRole('Superadmin')) {
            $data['organisasi_id'] = $this->resolveOrganisationId($request, $product->organisasi_id);
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($product->image);
            $data['image'] = null;
        }

        if ($request->hasFile('images')) {
            foreach (($product->images ?? []) as $old) {
                $this->deleteImage($old);
            }
            $data['images'] = $this->storeExtraImages($request);
        }

        $product->update($data);
        $this->syncVariations($product, $validated['variations'] ?? []);

        return redirect()->route('products.index')->with('success', 'Produk berjaya dikemaskini!');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk berjaya dipadam!');
    }

    /**
     * Validation rules shared by store() and update().
     */
    private function productRules(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0|lte:price',
            'postage_cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'images' => 'nullable|array|max:6',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'variations' => 'nullable|array',
            // The `id` keys must be declared, otherwise validate() strips them
            // from the validated payload and syncVariations() recreates every
            // row on each edit — which nulls order_items.product_variation_option_id
            // and loses the variant recorded against past orders.
            'variations.*.id' => 'nullable|integer',
            'variations.*.name' => 'required|string|max:255',
            'variations.*.type' => 'required|in:select,color',
            'variations.*.required' => 'nullable|boolean',
            'variations.*.options' => 'nullable|array',
            'variations.*.options.*.id' => 'nullable|integer',
            'variations.*.options.*.name' => 'required|string|max:255',
            'variations.*.options.*.price_adjustment' => 'nullable|numeric',
            'variations.*.options.*.stock' => 'nullable|integer|min:0',
            'variations.*.options.*.hex_color' => 'nullable|string|max:7',
        ];

        // A Superadmin has no organisation of their own, so they must state
        // which org sells (and gets paid for) the product. An org Admin's
        // product is always their own org, so they never send this field.
        if ($request->user()?->hasRole('Superadmin')) {
            $rules['organisasi_id'] = ['required', 'integer', 'exists:organizations,id'];
        }

        return $rules;
    }

    /**
     * Human-readable attribute names so validation errors read well in the UI.
     */
    private function validationAttributes(): array
    {
        return [
            'name' => 'nama produk',
            'organisasi_id' => 'organisasi penjual',
            'price' => 'harga',
            'member_price' => 'harga ahli',
            'postage_cost' => 'kos pos',
            'stock' => 'stok',
            'category_id' => 'kategori',
            'image' => 'gambar utama',
            'images' => 'gambar tambahan',
        ];
    }

    /**
     * Props that drive the "selling organisation" picker in the product form.
     *
     * The picker is Superadmin-only; org Admins are locked to their own org.
     * Each option carries whether the org has a live payment gateway so the
     * UI can warn before a product is published to an org that cannot be paid.
     */
    private function organizationProps(Request $request): array
    {
        $isSuperadmin = $request->user()->hasRole('Superadmin');

        if (! $isSuperadmin) {
            return ['isSuperadmin' => false, 'organizations' => []];
        }

        $organizations = Organization::orderBy('name')->get()->map(fn (Organization $org) => [
            'id' => $org->id,
            'name' => $org->name,
            'gateway' => $org->activeGateway(),
            'has_gateway' => $org->activeGateway() !== null,
        ]);

        return [
            'isSuperadmin' => true,
            'organizations' => $organizations,
        ];
    }

    /**
     * Decide which organisation owns (and gets paid for) the product.
     *
     * - Superadmin: the org they explicitly chose in the form.
     * - Org Admin:  always their own current organisation, ignoring any
     *   organisasi_id in the request so they cannot assign to another org.
     */
    private function resolveOrganisationId(Request $request, ?int $fallback = null): ?int
    {
        $user = $request->user();

        if ($user->hasRole('Superadmin')) {
            return $request->integer('organisasi_id') ?: $fallback;
        }

        return $user->current_organization_id ?? $fallback;
    }

    /**
     * Build the column payload common to store() and update().
     */
    private function productPayload(Request $request, array $validated): array
    {
        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'category_id' => $validated['category_id'],
            'status' => $request->boolean('status', true),
        ];

        $data['member_price'] = $this->nullableDecimal($validated['member_price'] ?? null);
        $data['postage_cost'] = $this->nullableDecimal($validated['postage_cost'] ?? null);

        return $data;
    }

    private function nullableDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Persist the extra gallery images and return their relative paths.
     *
     * @return array<int, string>
     */
    private function storeExtraImages(Request $request): array
    {
        $paths = [];

        foreach ((array) $request->file('images', []) as $img) {
            if ($img) {
                $paths[] = $img->store('products', 'public');
            }
        }

        return $paths;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * The Vue forms submit `variations` as a JSON string because the request is
     * sent as multipart/form-data (needed for the file inputs). Decode it back
     * into a real array and merge it into the request so the nested validation
     * rules apply instead of being silently skipped.
     */
    private function normalizeVariations(Request $request): void
    {
        $variations = $request->input('variations');

        if (is_string($variations)) {
            $decoded = json_decode($variations, true);
            $variations = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $variations = array_values(array_filter(
            (array) $variations,
            fn ($v) => is_array($v) && filled($v['name'] ?? null)
        ));

        foreach ($variations as $i => $variation) {
            $options = array_values(array_filter(
                (array) ($variation['options'] ?? []),
                fn ($o) => is_array($o) && filled($o['name'] ?? null)
            ));

            $variations[$i]['options'] = array_map(function ($option) {
                $option['price_adjustment'] = $this->nullableDecimal($option['price_adjustment'] ?? null);
                $option['stock'] = ($option['stock'] ?? '') === '' ? null : $option['stock'];
                $option['hex_color'] = filled($option['hex_color'] ?? null) ? $option['hex_color'] : null;

                return $option;
            }, $options);

            $variations[$i]['required'] = filter_var(
                $variation['required'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            );
        }

        $request->merge(['variations' => $variations]);
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

            if (! empty($variation['id'])) {
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
                // Use a null/'' check rather than empty(): a stock of 0 means
                // "out of stock", but empty() would coerce it to null, which
                // this schema treats as "unlimited stock" and would let buyers
                // keep ordering a sold-out option.
                $optData = [
                    'name' => $option['name'],
                    'price_adjustment' => $this->nullableDecimal($option['price_adjustment'] ?? null),
                    'stock' => ($option['stock'] ?? null) === null || $option['stock'] === ''
                        ? null
                        : (int) $option['stock'],
                    'hex_color' => $option['hex_color'] ?? null,
                    'sort_order' => $oIndex,
                ];

                if (! empty($option['id'])) {
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
