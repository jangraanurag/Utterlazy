<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Restaurant $restaurant;

    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->restaurant = Restaurant::factory()->create();
        $this->menuItem = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 250,
        ]);
    }

    public function test_user_can_get_empty_cart()
    {
        $response = $this->actingAs($this->user)->getJson('/api/cart');

        $response->assertOk();
        $response->assertJson(['cart' => null, 'items' => [], 'subtotal' => 0]);
    }

    public function test_user_can_add_item_to_cart()
    {
        $response = $this->actingAs($this->user)->postJson('/api/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Item added to cart']);
        $this->assertDatabaseHas('cart_items', ['menu_item_id' => $this->menuItem->id, 'quantity' => 2]);
    }

    public function test_user_can_update_cart_item_quantity()
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'restaurant_id' => $this->restaurant->id]);
        $cartItem = CartItem::create(['cart_id' => $cart->id, 'menu_item_id' => $this->menuItem->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->patchJson("/api/cart/items/{$cartItem->id}", [
            'quantity' => 5,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 5]);
    }

    public function test_user_can_remove_cart_item()
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'restaurant_id' => $this->restaurant->id]);
        $cartItem = CartItem::create(['cart_id' => $cart->id, 'menu_item_id' => $this->menuItem->id, 'quantity' => 1]);

        $response = $this->actingAs($this->user)->deleteJson("/api/cart/items/{$cartItem->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    public function test_user_can_clear_cart()
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'restaurant_id' => $this->restaurant->id]);
        CartItem::create(['cart_id' => $cart->id, 'menu_item_id' => $this->menuItem->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->deleteJson('/api/cart');

        $response->assertOk();
        $this->assertDatabaseMissing('carts', ['user_id' => $this->user->id]);
    }
}
