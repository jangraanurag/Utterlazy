<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRestaurantCartTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Restaurant $restaurant1;

    protected Restaurant $restaurant2;

    protected MenuItem $item1;

    protected MenuItem $item2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->restaurant1 = Restaurant::factory()->create();
        $this->restaurant2 = Restaurant::factory()->create();
        $this->item1 = MenuItem::factory()->create(['restaurant_id' => $this->restaurant1->id, 'price' => 200]);
        $this->item2 = MenuItem::factory()->create(['restaurant_id' => $this->restaurant2->id, 'price' => 300]);
    }

    public function test_adding_item_from_different_restaurant_clears_cart()
    {
        // Add item from restaurant 1
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->item1->id,
            'quantity' => 2,
        ]);

        // Verify cart has restaurant 1 item
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertEquals($this->restaurant1->id, $cart->restaurant_id);

        // Add item from restaurant 2 — should clear old items
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->item2->id,
            'quantity' => 1,
        ]);

        // Verify cart now has only restaurant 2 item
        $cart->refresh();
        $this->assertEquals($this->restaurant2->id, $cart->restaurant_id);
        $this->assertCount(1, $cart->items);
        $this->assertEquals($this->item2->id, $cart->items->first()->menu_item_id);
    }

    public function test_cart_subtotal_calculates_correctly()
    {
        // Add 2 items from restaurant 1
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->item1->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/cart');

        $response->assertOk();
        $this->assertEquals(600, $response->json('subtotal')); // 200 * 3
    }

    public function test_cart_item_quantity_update()
    {
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->item1->id,
            'quantity' => 1,
        ]);

        $cart = Cart::where('user_id', $this->user->id)->first();
        $cartItem = $cart->items->first();

        $response = $this->actingAs($this->user)->patchJson("/api/cart/items/{$cartItem->id}", [
            'quantity' => 5,
        ]);

        $response->assertOk();
        $this->assertEquals(1000, $response->json('subtotal')); // 200 * 5
    }

    public function test_checkout_clears_cart()
    {
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->item1->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'restaurant_id' => $this->restaurant1->id,
            'items' => [['menu_item_id' => $this->item1->id, 'quantity' => 2]],
            'payment_mode' => 'cod',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/cart');
        $this->assertNull($response->json('cart'));
    }
}
