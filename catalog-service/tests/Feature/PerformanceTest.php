<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->restaurant = Restaurant::factory()->create();
    }

    public function test_restaurant_list_endpoint_performance()
    {
        Restaurant::factory()->count(50)->create();

        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/restaurants');
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, "Restaurant list took {$elapsed}s for 10 requests");
    }

    public function test_checkout_endpoint_performance()
    {
        $menuItems = MenuItem::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $start = microtime(true);
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)->postJson('/api/checkout', [
                'restaurant_id' => $this->restaurant->id,
                'items' => [['menu_item_id' => $menuItems->first()->id, 'quantity' => 1]],
                'payment_mode' => 'cod',
            ]);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(10.0, $elapsed, "Checkout took {$elapsed}s for 5 orders");
    }

    public function test_order_list_pagination_performance()
    {
        Order::factory()->count(100)->create(['user_id' => $this->user->id]);

        $start = microtime(true);
        $this->actingAs($this->user)->getJson('/api/orders?page=1&per_page=15');
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.0, $elapsed, "Order list pagination took {$elapsed}s");
        $this->assertLessThanOrEqual(15, count($this->actingAs($this->user)->getJson('/api/orders')->json('data')));
    }

    public function test_search_endpoint_performance()
    {
        Restaurant::factory()->count(20)->create();

        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/search?q=restaurant');
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(3.0, $elapsed, "Search took {$elapsed}s for 10 queries");
    }

    public function test_concurrent_cart_operations()
    {
        $menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $start = microtime(true);
        $promises = [];
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($this->user)->postJson('/api/cart/items', [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, "20 cart adds took {$elapsed}s");
    }

    public function test_database_query_count_checkout()
    {
        $menuItem = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($this->user)->postJson('/api/checkout', [
            'restaurant_id' => $this->restaurant->id,
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 2]],
            'payment_mode' => 'cod',
        ]);

        $this->assertLessThanOrEqual(30, $queries, "Checkout used {$queries} queries (target: ≤30)");
    }
}
