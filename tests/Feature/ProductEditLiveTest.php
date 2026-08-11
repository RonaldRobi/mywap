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
 * Reproduces the reported symptom: "as Admin and Superadmin I cannot edit a
 * product that is already live". Exercises the full HTTP round trip
 * (middleware -> policy -> controller -> validation) rather than the policy in
 * isolation, because the earlier policy-only assertions passed while the real
 * request still failed.
 */
class ProductEditLiveTest extends TestCase
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

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        // Mirrors production: the Superadmin's current_organization_id points
        // at one specific org, not at every org they administer.
        $this->superadmin = User::factory()->create([
            'current_organization_id' => $this->orgB->id,
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

    private function liveProduct(?int $orgId): Product
    {
        return Product::create([
            'name' => 'Produk Live',
            'description' => 'Sedia dijual',
            'price' => 99.90,
            'stock' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => $orgId,
            'status' => true,
        ]);
    }

    public function test_superadmin_can_open_edit_form_for_live_product_of_any_org(): void
    {
        foreach ([$this->orgA->id, $this->orgB->id, null] as $orgId) {
            $product = $this->liveProduct($orgId);

            $this->actingAs($this->superadmin)
                ->get(route('products.edit', $product))
                ->assertOk();
        }
    }

    public function test_admin_can_open_edit_form_for_live_product_of_own_org(): void
    {
        $product = $this->liveProduct($this->orgA->id);

        $this->actingAs($this->adminA)
            ->get(route('products.edit', $product))
            ->assertOk();
    }

    /**
     * The critical path: actually submitting the edit form for a live product.
     */
    public function test_superadmin_can_submit_update_for_live_product(): void
    {
        $product = $this->liveProduct($this->orgA->id);

        $this->actingAs($this->superadmin)
            ->put(route('products.update', $product), [
                'name' => 'Produk Live Dikemaskini',
                'description' => 'Deskripsi baharu',
                'price' => '120.00',
                'stock' => '8',
                'category_id' => (string) $this->category->id,
                'organisasi_id' => (string) $this->orgA->id,
                'status' => '1',
                'variations' => '[]',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertSame('Produk Live Dikemaskini', $product->fresh()->name);
    }

    public function test_admin_can_submit_update_for_live_product(): void
    {
        $product = $this->liveProduct($this->orgA->id);

        $this->actingAs($this->adminA)
            ->put(route('products.update', $product), [
                'name' => 'Diedit Oleh Admin',
                'price' => '55.00',
                'stock' => '3',
                'category_id' => (string) $this->category->id,
                'status' => '1',
                'variations' => '[]',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Diedit Oleh Admin', $product->fresh()->name);
    }

    /**
     * Editing a product that already has variations must not wipe or duplicate
     * them when the form round-trips the existing rows back.
     */
    public function test_editing_product_preserves_existing_variations(): void
    {
        $product = $this->liveProduct($this->orgA->id);
        $variation = $product->variations()->create([
            'name' => 'Saiz',
            'type' => 'select',
            'required' => true,
            'sort_order' => 0,
        ]);
        $optionS = $variation->options()->create(['name' => 'S', 'stock' => 4, 'sort_order' => 0]);
        $optionM = $variation->options()->create(['name' => 'M', 'stock' => 6, 'sort_order' => 1]);

        $payload = json_encode([
            [
                'id' => $variation->id,
                'name' => 'Saiz',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['id' => $optionS->id, 'name' => 'S', 'price_adjustment' => '', 'stock' => '4', 'hex_color' => ''],
                    ['id' => $optionM->id, 'name' => 'M', 'price_adjustment' => '', 'stock' => '6', 'hex_color' => ''],
                ],
            ],
        ]);

        $this->actingAs($this->superadmin)
            ->put(route('products.update', $product), [
                'name' => 'Produk Live',
                'price' => '99.90',
                'stock' => '10',
                'category_id' => (string) $this->category->id,
                'organisasi_id' => (string) $this->orgA->id,
                'status' => '1',
                'variations' => $payload,
            ])
            ->assertSessionHasNoErrors();

        $product->refresh()->load('variations.options');
        $this->assertCount(1, $product->variations, 'Variation was duplicated or dropped.');
        $this->assertCount(2, $product->variations->first()->options);
        $this->assertSame($variation->id, $product->variations->first()->id);
    }

    /**
     * Updating without choosing a new file must keep the existing image rather
     * than silently blanking it.
     */
    public function test_update_without_new_image_keeps_existing_image(): void
    {
        $product = $this->liveProduct($this->orgA->id);
        $product->update(['image' => 'products/existing.jpg']);

        $this->actingAs($this->superadmin)
            ->put(route('products.update', $product), [
                'name' => 'Produk Live',
                'price' => '99.90',
                'stock' => '10',
                'category_id' => (string) $this->category->id,
                'organisasi_id' => (string) $this->orgA->id,
                'status' => '1',
                'variations' => '[]',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('products/existing.jpg', $product->fresh()->image);
    }

    /**
     * Likewise the gallery must survive an edit that touches no files.
     */
    public function test_update_without_new_gallery_keeps_existing_gallery(): void
    {
        $product = $this->liveProduct($this->orgA->id);
        $product->update(['images' => ['products/a.jpg', 'products/b.jpg']]);

        $this->actingAs($this->superadmin)
            ->put(route('products.update', $product), [
                'name' => 'Produk Live',
                'price' => '99.90',
                'stock' => '10',
                'category_id' => (string) $this->category->id,
                'organisasi_id' => (string) $this->orgA->id,
                'status' => '1',
                'variations' => '[]',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['products/a.jpg', 'products/b.jpg'], $product->fresh()->images);
    }

    /**
     * A Member must never reach the edit form.
     */
    public function test_member_cannot_access_edit_form(): void
    {
        $member = User::factory()->create([
            'current_organization_id' => $this->orgA->id,
            'profile_completed_at' => now(),
        ]);
        $member->assignRole('Member');

        $product = $this->liveProduct($this->orgA->id);

        $this->actingAs($member)
            ->get(route('products.edit', $product))
            ->assertForbidden();
    }
}
