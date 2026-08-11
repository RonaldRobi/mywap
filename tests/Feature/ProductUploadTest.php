<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductUploadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create();

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->category = Category::create(['name' => 'Baju']);
    }

    /**
     * Reproduces the reported bug: admin submits the Create Product form with
     * no variations. Inertia's forceFormData serialises `variations` as the
     * JSON string "[]", which fails the `nullable|array` rule. The product is
     * never created and the admin is bounced back with an error they never see.
     */
    public function test_admin_can_create_product_without_variations(): void
    {
        $response = $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Baju Melayu',
            'description' => 'Baju raya',
            'price' => '99.90',
            'postage_cost' => '10',
            'stock' => '25',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => '[]',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Baju Melayu',
            'stock' => 25,
            'status' => 1,
        ]);
    }

    /**
     * Same path, but with variations present as a JSON string.
     */
    public function test_admin_can_create_product_with_variations(): void
    {
        $variations = json_encode([
            [
                'id' => null,
                'name' => 'Saiz',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['id' => null, 'name' => 'S', 'price_adjustment' => '', 'stock' => '5', 'hex_color' => ''],
                    ['id' => null, 'name' => 'M', 'price_adjustment' => '5.00', 'stock' => '3', 'hex_color' => ''],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Kurta',
            'price' => '120',
            'stock' => '8',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => $variations,
        ]);

        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Kurta')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->variations);
        $this->assertCount(2, $product->variations->first()->options);
    }

    /**
     * A newly created product must be visible on the public mall listing.
     */
    public function test_new_product_appears_on_public_mall(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Songkok',
            'price' => '35',
            'stock' => '10',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => '[]',
        ])->assertSessionHasNoErrors();

        $this->get(route('mall.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ecommerce/Products/Index')
                ->where('products.data.0.name', 'Songkok')
            );
    }

    /**
     * Image upload should persist to the public disk and be readable back.
     */
    public function test_product_image_is_stored_on_public_disk(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Sejadah',
            'price' => '80',
            'stock' => '4',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => '[]',
            'image' => UploadedFile::fake()->image('sejadah.jpg', 800, 800),
        ])->assertSessionHasNoErrors();

        $product = Product::where('name', 'Sejadah')->firstOrFail();

        $this->assertNotNull($product->image, 'Product image path was not saved.');
        Storage::disk('public')->assertExists($product->image);
    }

    /**
     * An Admin must be able to edit a product belonging to their own org.
     * ProductPolicy compares against $user->organisasi_id which does not exist
     * on the users table (the real column is current_organization_id).
     */
    public function test_admin_can_edit_own_organisation_product(): void
    {
        $product = Product::create([
            'name' => 'Kopiah',
            'price' => 20,
            'stock' => 5,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('products.edit', $product))
            ->assertOk();
    }

    /**
     * An Admin from a different organisation must not be able to edit.
     */
    public function test_admin_cannot_edit_other_organisation_product(): void
    {
        $otherOrg = Organization::factory()->create();

        $product = Product::create([
            'name' => 'Produk Org Lain',
            'price' => 20,
            'stock' => 5,
            'category_id' => $this->category->id,
            'organisasi_id' => $otherOrg->id,
            'status' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('products.edit', $product))
            ->assertForbidden();
    }

    /**
     * Draft products stay off the public mall but remain visible to the admin
     * in the catalogue, so a saved draft never looks like it vanished.
     */
    public function test_draft_product_is_hidden_from_mall_but_visible_to_admin(): void
    {
        Product::create([
            'name' => 'Produk Draf',
            'price' => 15,
            'stock' => 3,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => false,
        ]);

        $this->get(route('mall.index'))
            ->assertInertia(fn ($page) => $page->where('products.total', 0));

        $this->actingAs($this->admin)
            ->get(route('products.index'))
            ->assertInertia(fn ($page) => $page->where('products.total', 1));
    }

    /**
     * Toggling a product to draft must actually persist as inactive.
     */
    public function test_product_can_be_unpublished(): void
    {
        $product = Product::create([
            'name' => 'Selendang',
            'price' => 30,
            'stock' => 6,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => true,
        ]);

        $this->actingAs($this->admin)->put(route('products.update', $product), [
            'name' => 'Selendang',
            'price' => '30',
            'stock' => '6',
            'category_id' => (string) $this->category->id,
            'status' => '0',
            'variations' => '[]',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->status);
    }

    /**
     * A draft product must not be purchasable through checkout.
     */
    public function test_draft_product_cannot_be_purchased(): void
    {
        $product = Product::create([
            'name' => 'Produk Tersembunyi',
            'price' => 40,
            'stock' => 10,
            'category_id' => $this->category->id,
            'status' => false,
        ]);

        $this->post(route('mall.checkout'), [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'Kuala Lumpur',
        ])->assertSessionHasErrors('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    /**
     * A draft product must 404 for buyers but stay previewable for admins.
     */
    public function test_draft_product_detail_is_hidden_from_public(): void
    {
        $product = Product::create([
            'name' => 'Draf Sulit',
            'price' => 40,
            'stock' => 2,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => false,
        ]);

        $this->get(route('mall.show', $product))->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('products.show', $product))
            ->assertOk();
    }

    /**
     * member_price above the list price is nonsensical and must be rejected.
     */
    public function test_member_price_cannot_exceed_normal_price(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Harga Salah',
            'price' => '50',
            'member_price' => '80',
            'stock' => '1',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => '[]',
        ])->assertSessionHasErrors('member_price');

        $this->assertDatabaseMissing('products', ['name' => 'Harga Salah']);
    }
}
