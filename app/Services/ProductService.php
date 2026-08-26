<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * ProductService
 *
 * Logik tunggal untuk domain E-commerce (produk & kategori) — dikongsi oleh
 * WebController (Inertia) dan ApiController (JSON) supaya web & Flutter tidak
 * drift. Rujuk docs/FLUTTER_PLAN.md §4.
 */
class ProductService
{
    /**
     * Serialize satu Product kepada bentuk konsisten untuk web & API.
     * `is_member` = pembeli yang request ini layak harga ahli (produk ada
     * member_price dan pengguna berdaftar).
     */
    public function serialize(Product $product, ?User $user = null): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'member_price' => $product->member_price,
            'postage_cost' => $product->postage_cost,
            'stock' => $product->stock,
            'category_id' => $product->category_id,
            'organisasi_id' => $product->organisasi_id,
            'image' => $product->image,
            'images' => $product->images ?? [],
            'status' => $product->status,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'variations_count' => $product->variations_count ?? 0,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'organization' => $product->organization ? [
                'id' => $product->organization->id,
                'name' => $product->organization->name,
            ] : null,
            'is_member' => $user !== null && $product->member_price !== null,
            'price_for_member' => $product->member_price !== null ? (float) $product->member_price : null,
        ];
    }

    /**
     * Serialize produk + variasi (untuk halaman show). Memerlukan hubungan
     * `variations.options` dimuatkan oleh pemanggil.
     */
    public function serializeWithVariations(Product $product, ?User $user = null): array
    {
        $data = $this->serialize($product, $user);

        $data['variations'] = $product->relationLoaded('variations')
            ? $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'required' => $v->required,
                'sort_order' => $v->sort_order,
                'options' => $v->options->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'price_adjustment' => $o->price_adjustment,
                    'stock' => $o->stock,
                    'hex_color' => $o->hex_color,
                    'image' => $o->image,
                    'sort_order' => $o->sort_order,
                ])->values(),
            ])->values()
            : [];

        return $data;
    }

    /**
     * Senarai produk (mall/admin) mengikut penapis search, category_id, sort.
     * Item paginator sudah diserialize. Pembeli hanya nampak produk aktif;
     * pentadbir nampak draf dalam katalog (kecuali `isMall`).
     */
    public function list(Request $request, ?User $user = null, bool $isMall = false): LengthAwarePaginator
    {
        $query = Product::with('category', 'organization')->withCount('variations');

        $isAdmin = $user && $user->hasRole(['Superadmin', 'Admin']);

        if ($isMall || ! $isAdmin) {
            $query->active();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
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

        return $query->paginate(self::perPage($request))->withQueryString()->through(
            fn (Product $product) => $this->serialize($product, $user)
        );
    }

    /**
     * Payload penuh untuk halaman/endpoint show produk: produk (dengan variasi)
     * + produk berkaitan dalam kategori yang sama.
     */
    public function showDetail(Product $product, ?User $user = null): array
    {
        $product->loadMissing([
            'category',
            'organization',
            'variations.options',
        ])->loadCount('variations');

        $relatedProducts = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['category', 'organization'])
            ->withCount('variations')
            ->limit(4)
            ->get()
            ->map(fn ($p) => $this->serialize($p, $user));

        return [
            'product' => $this->serializeWithVariations($product, $user),
            'relatedProducts' => $relatedProducts->values(),
        ];
    }

    /**
     * Senarai kategori berpaginate (halaman admin + API).
     */
    public function categories(): LengthAwarePaginator
    {
        return Category::orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Category $category) => $this->serializeCategory($category));
    }

    /**
     * Pilihan kategori untuk penapis produk (tidak berpaginate).
     */
    public function categoryOptions()
    {
        return Category::orderBy('name')->get();
    }

    public function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }

    /**
     * Pastikan per_page hanya nilai yang dibenarkan (25/50/100 default 12).
     */
    public static function perPage(Request $request, int $default = 12): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [12, 25, 50, 100]) ? $perPage : $default;
    }
}
