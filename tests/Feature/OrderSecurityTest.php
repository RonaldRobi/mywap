<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers authorisation and data-integrity around orders.
 */
class OrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->org = Organization::factory()->create();
        $this->category = Category::create(['name' => 'Baju']);
    }

    private function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Baju',
            'price' => 50,
            'stock' => 20,
            'postage_cost' => 10,
            'category_id' => $this->category->id,
            'organisasi_id' => $this->org->id,
            'status' => true,
        ], $attrs));
    }

    private function guestOrder(): Order
    {
        $this->post(route('mall.checkout'), [
            'products' => [['id' => $this->product()->id, 'quantity' => 1]],
            'shipping_name' => 'Siti Aminah',
            'shipping_phone' => '0198887777',
            'shipping_address' => 'No 5, Jalan Rahsia, Shah Alam',
            'shipping_postcode' => '40000',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * /mall/orders/{order} takes a sequential integer ID and performs no
     * authorisation, so anyone can walk the IDs and harvest every customer's
     * name, phone number and home address.
     */
    public function test_stranger_cannot_read_another_persons_guest_order(): void
    {
        $order = $this->guestOrder();

        $this->get(route('mall.order.show', $order))
            ->assertForbidden();
    }

    /**
     * The buyer themselves must still be able to open their receipt.
     */
    public function test_buyer_can_view_their_own_guest_order_via_signed_link(): void
    {
        $order = $this->guestOrder();

        $this->get($order->publicUrl())->assertOk();
    }

    /**
     * A logged-in member must not read another member's order.
     */
    public function test_member_cannot_view_another_members_order(): void
    {
        $owner = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $owner->assignRole('Member');

        $intruder = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $intruder->assignRole('Member');

        $order = Order::create([
            'user_id' => $owner->id,
            'organisasi_id' => $this->org->id,
            'total' => 50,
            'postage_cost' => 10,
            'status' => 'pending',
        ]);

        $this->actingAs($intruder)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    /**
     * Order status must be constrained to the known lifecycle values, not any
     * arbitrary string the client cares to send.
     */
    public function test_order_status_must_be_a_known_value(): void
    {
        $admin = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $admin->assignRole('Admin');

        $order = Order::create([
            'user_id' => null,
            'organisasi_id' => $this->org->id,
            'total' => 50,
            'postage_cost' => 10,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('orders.updateStatus', $order), ['status' => 'bogus-status'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $order->fresh()->status);
    }

    /**
     * Postage must be charged consistently. The mall checkout deliberately
     * charges the single highest postage across the cart; the dashboard
     * order path must not silently sum them instead.
     */
    public function test_postage_is_combined_consistently_across_checkout_paths(): void
    {
        $a = $this->product(['name' => 'A', 'postage_cost' => 10]);
        $b = $this->product(['name' => 'B', 'postage_cost' => 8]);

        $member = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $member->assignRole('Member');

        $this->actingAs($member)->post(route('orders.store'), [
            'products' => [
                ['id' => $a->id, 'quantity' => 1],
                ['id' => $b->id, 'quantity' => 1],
            ],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'KL',
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $this->assertEquals(10.0, (float) $order->postage_cost);
    }

    /**
     * Cancelling an order must return the reserved stock to the catalogue,
     * otherwise inventory leaks away with every abandoned order.
     */
    public function test_cancelling_an_order_restores_stock(): void
    {
        $product = $this->product(['stock' => 20]);

        $admin = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $admin->assignRole('Admin');

        $this->post(route('mall.checkout'), [
            'products' => [['id' => $product->id, 'quantity' => 3]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'KL',
        ])->assertSessionHasNoErrors();

        $this->assertSame(17, $product->fresh()->stock);

        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('orders.updateStatus', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $product->fresh()->stock, 'Stock was not returned after cancellation.');
    }

    /**
     * A guest order must be attributed to the organisation selling the goods,
     * otherwise it lands with a null organisasi_id and that org's Admins can
     * neither see nor action it.
     */
    public function test_guest_order_is_attributed_to_selling_organisation(): void
    {
        $product = $this->product();

        $this->post(route('mall.checkout'), [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'KL',
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame($this->org->id, $order->organisasi_id);

        $admin = User::factory()->create(['current_organization_id' => $this->org->id, 'profile_completed_at' => now()]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('orders.show', $order))
            ->assertOk();
    }

    /**
     * Ordering more than the available stock must fail cleanly.
     */
    public function test_cannot_order_more_than_available_stock(): void
    {
        $product = $this->product(['stock' => 2]);

        $this->post(route('mall.checkout'), [
            'products' => [['id' => $product->id, 'quantity' => 5]],
            'shipping_name' => 'Ali',
            'shipping_phone' => '0123456789',
            'shipping_address' => 'KL',
        ])->assertSessionHasErrors('error');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }
}
