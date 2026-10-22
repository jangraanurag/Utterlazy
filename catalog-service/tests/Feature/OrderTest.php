<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Restaurant $restaurant;

    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['khaalo_coins' => 100]);
        $this->restaurant = Restaurant::factory()->create();
        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 200,
        ]);
    }

    public function test_user_can_checkout()
    {
        $response = $this->actingAs($this->user)->postJson('/api/checkout', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                ['menu_item_id' => $this->menuItem->id, 'quantity' => 2],
            ],
            'payment_mode' => 'cod',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message', 'order', 'delivery_eta_minutes',
        ]);
        $this->assertDatabaseHas('orders', ['restaurant_id' => $this->restaurant->id]);
    }

    public function test_user_can_checkout_with_coupon()
    {
        $coupon = Coupon::create([
            'code' => 'TEST10',
            'title' => '10% Off',
            'type' => 'percentage',
            'value' => 10,
            'min_order' => 100,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/checkout', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                ['menu_item_id' => $this->menuItem->id, 'quantity' => 2],
            ],
            'payment_mode' => 'cod',
            'coupon_code' => 'TEST10',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('coupon_discount', fn ($val) => $val > 0);
    }

    public function test_user_can_list_orders()
    {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/orders');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_cancel_pending_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_user_cannot_cancel_preparing_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'preparing',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_user_can_get_order_details()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonStructure(['order' => ['id', 'status', 'grand_total']]);
    }
}
