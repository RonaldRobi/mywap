<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EcommerceApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Category $category;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'payment_gateway' => null]);
        $this->category = Category::create(['name' => 'Baju']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
            'phone' => '0123456789',
        ]);
        $this->member->assignRole('Member');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Baju Melayu',
            'description' => 'Baju raya',
            'price' => 100,
            'member_price' => 80,
            'stock' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => true,
        ], $attrs));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
        $this->getJson('/api/v1/categories')->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->postJson('/api/v1/orders', [])->assertStatus(401);
    }

    public function test_member_can_list_products_with_pagination_envelope(): void
    {
        $this->product(['name' => 'Baju A']);
        $this->product(['name' => 'Baju B']);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'price', 'member_price', 'stock', 'category', 'organization', 'is_member'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.is_member', true);
    }

    public function test_draft_products_are_hidden_from_member_listing(): void
    {
        $this->product(['name' => 'Aktif', 'status' => true]);
        $this->product(['name' => 'Draf', 'status' => false]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Aktif');
    }

    public function test_products_can_be_filtered_by_search_and_category(): void
    {
        $this->product(['name' => 'Songkok']);
        $this->product(['name' => 'Sejadah']);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/products?search=Songkok')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Songkok');

        $this->getJson('/api/v1/products?category_id='.$this->category->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_member_can_view_product_detail_with_variations_and_related(): void
    {
        $product = $this->product(['name' => 'Baju Kain']);
        $variation = $product->variations()->create([
            'name' => 'Saiz',
            'type' => 'select',
            'required' => true,
            'sort_order' => 0,
        ]);
        $variation->options()->create(['name' => 'S', 'stock' => 4, 'sort_order' => 0]);
        $this->product(['name' => 'Baju Lain']);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.is_member', true)
            ->assertJsonPath('data.product.variations.0.name', 'Saiz')
            ->assertJsonPath('data.product.variations.0.options.0.name', 'S')
            ->assertJsonStructure([
                'data' => [
                    'product' => ['id', 'name', 'price', 'member_price', 'variations'],
                    'relatedProducts' => ['*' => ['id', 'name']],
                ],
            ]);
    }

    public function test_member_cannot_view_draft_product_detail(): void
    {
        $product = $this->product(['name' => 'Draf', 'status' => false]);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    public function test_member_can_list_categories_with_pagination_envelope(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'description']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Baju');
    }

    public function test_member_can_create_order_with_member_price_and_stock_decremented(): void
    {
        $product = $this->product(['price' => 100, 'member_price' => 80, 'stock' => 10]);

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/orders', [
            'products' => [['id' => $product->id, 'quantity' => 2]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'Kuala Lumpur',
        ])
            ->assertOk()
            ->assertJsonPath('data.order.total', '160.00')
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.payment_url', null);

        $order = Order::firstOrFail();
        $this->assertSame($this->member->id, $order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame(8, $product->fresh()->stock);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 80,
        ]);
    }

    public function test_checkout_validation_matches_web_rules(): void
    {
        $product = $this->product();

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/orders', [
            'products' => [['id' => 99999, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_member_can_pay_pending_order_without_gateway(): void
    {
        $product = $this->product(['price' => 50, 'member_price' => null, 'stock' => 5]);

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/orders', [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
        ])->assertOk();

        $order = Order::firstOrFail();

        $this->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.payment_url', null);

        $this->assertSame('paid', $order->fresh()->status);

        $this->assertDatabaseHas('payments', [
            'payable_type' => 'order',
            'payable_id' => $order->id,
            'amount' => 50.00,
            'status' => 'successful',
            'gateway' => 'dummy',
        ]);
    }

    public function test_cannot_pay_an_order_twice(): void
    {
        $product = $this->product(['member_price' => null]);

        Sanctum::actingAs($this->member);
        $this->postJson('/api/v1/orders', [
            'products' => [['id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $order = Order::firstOrFail();

        $this->postJson("/api/v1/orders/{$order->id}/pay")->assertOk();
        $this->postJson("/api/v1/orders/{$order->id}/pay")->assertStatus(422);
    }

    public function test_member_cannot_view_another_members_order(): void
    {
        $product = $this->product();

        Sanctum::actingAs($this->member);
        $this->postJson('/api/v1/orders', [
            'products' => [['id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $order = Order::firstOrFail();

        $intruder = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $intruder->assignRole('Member');

        Sanctum::actingAs($intruder);

        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(403);
        $this->postJson("/api/v1/orders/{$order->id}/pay")->assertStatus(403);
    }
}
