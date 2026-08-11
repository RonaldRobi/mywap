<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A Superadmin has no organisation of their own, so when they publish a product
 * they must choose which organisation sells it — that is what routes the eventual
 * payment to the correct gateway. An org Admin is always locked to their own org.
 */
class SuperadminProductOrgTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $superadmin;

    private User $adminA;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->orgA = Organization::factory()->create(['name' => 'PKPIM']);
        $this->orgB = Organization::factory()->create(['name' => 'ABIM']);

        // Superadmin's own current org is irrelevant to product ownership.
        $this->superadmin = User::factory()->create([
            'current_organization_id' => null,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->adminA = User::factory()->create([
            'current_organization_id' => $this->orgA->id,
            'profile_completed_at' => now(),
        ]);
        $this->adminA->assignRole('Admin');

        $this->category = Category::create(['name' => 'Baju']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Produk Baharu',
            'price' => '50',
            'stock' => '10',
            'category_id' => (string) $this->category->id,
            'status' => '1',
            'variations' => '[]',
        ], $overrides);
    }

    public function test_superadmin_must_choose_selling_organisation(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('products.store'), $this->payload())
            ->assertSessionHasErrors('organisasi_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_superadmin_product_is_assigned_to_chosen_organisation(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('products.store'), $this->payload([
                'organisasi_id' => (string) $this->orgB->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertSame($this->orgB->id, Product::firstOrFail()->organisasi_id);
    }

    public function test_superadmin_cannot_choose_a_nonexistent_organisation(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('products.store'), $this->payload([
                'organisasi_id' => '99999',
            ]))
            ->assertSessionHasErrors('organisasi_id');
    }

    public function test_admin_product_ignores_submitted_org_and_uses_own(): void
    {
        // Even if a crafted request tries to assign to org B, an Admin's
        // product must stay with their own org (A).
        $this->actingAs($this->adminA)
            ->post(route('products.store'), $this->payload([
                'organisasi_id' => (string) $this->orgB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->orgA->id, Product::firstOrFail()->organisasi_id);
    }

    public function test_superadmin_can_reassign_organisation_on_edit(): void
    {
        $product = Product::create([
            'name' => 'Produk Live',
            'price' => 50,
            'stock' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->orgA->id,
            'status' => true,
        ]);

        $this->actingAs($this->superadmin)
            ->put(route('products.update', $product), $this->payload([
                'name' => 'Produk Live',
                'organisasi_id' => (string) $this->orgB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->orgB->id, $product->fresh()->organisasi_id);
    }

    public function test_admin_edit_cannot_move_product_to_another_org(): void
    {
        $product = Product::create([
            'name' => 'Produk A',
            'price' => 50,
            'stock' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->orgA->id,
            'status' => true,
        ]);

        $this->actingAs($this->adminA)
            ->put(route('products.update', $product), $this->payload([
                'name' => 'Produk A',
                'organisasi_id' => (string) $this->orgB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->orgA->id, $product->fresh()->organisasi_id);
    }

    public function test_create_form_gives_superadmin_the_org_list(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('products.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Ecommerce/Products/Create')
                ->where('isSuperadmin', true)
                ->has('organizations', 2)
            );
    }

    public function test_create_form_hides_org_list_from_org_admin(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('products.create'))
            ->assertInertia(fn ($page) => $page
                ->where('isSuperadmin', false)
                ->has('organizations', 0)
            );
    }

    public function test_product_without_org_cannot_be_purchased(): void
    {
        // Simulates a legacy/orphaned product with no selling org.
        $product = Product::create([
            'name' => 'Produk Yatim',
            'price' => 50,
            'stock' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => null,
            'status' => true,
        ]);

        $this->post(route('mall.checkout'), [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'KL',
        ])->assertSessionHasErrors('error');

        $this->assertDatabaseCount('orders', 0);
    }
}
