<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RestaurantOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_vendor_can_pause_orders()
    {
        $restaurant = Restaurant::factory()->create([
            'is_accepting_orders' => true,
            'paused_until' => null,
            'manually_offline' => false,
            'latitude' => 12.9716,
            'longitude' => 77.5946,
        ]);

        $response = $this->actingAs($restaurant, 'sanctum')
            ->postJson('/api/vendor/pause', [
                'pause_duration' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('is_accepting_orders', false)
            ->assertJsonPath('manually_offline', false);

        $restaurant->refresh();
        $this->assertFalse($restaurant->is_accepting_orders);
        $this->assertNotNull($restaurant->paused_until);
        $this->assertTrue(Cache::has('restaurant_pause_'.$restaurant->id));
    }

    public function test_vendor_can_resume_orders()
    {
        $restaurant = Restaurant::factory()->create([
            'is_accepting_orders' => false,
            'paused_until' => now()->addMinutes(30),
            'manually_offline' => false,
            'latitude' => 12.9716,
            'longitude' => 77.5946,
        ]);

        Cache::put('restaurant_pause_'.$restaurant->id, true, now()->addMinutes(30));

        $response = $this->actingAs($restaurant, 'sanctum')
            ->postJson('/api/vendor/resume');

        $response->assertStatus(200)
            ->assertJsonPath('is_accepting_orders', true)
            ->assertJsonPath('paused_until', null);

        $restaurant->refresh();
        $this->assertTrue($restaurant->is_accepting_orders);
        $this->assertNull($restaurant->paused_until);
        $this->assertFalse(Cache::has('restaurant_pause_'.$restaurant->id));
    }

    public function test_artisan_daily_reset_command()
    {
        $restaurant = Restaurant::factory()->create([
            'is_accepting_orders' => false,
            'paused_until' => now()->addMinutes(30),
            'manually_offline' => true,
            'is_active' => true,
        ]);

        Cache::put('restaurant_pause_'.$restaurant->id, true, now()->addMinutes(30));

        $this->artisan('restaurants:daily-reset')
            ->assertSuccessful();

        $restaurant->refresh();
        $this->assertFalse($restaurant->manually_offline);
        $this->assertNull($restaurant->paused_until);
        $this->assertFalse(Cache::has('restaurant_pause_'.$restaurant->id));
    }

    public function test_sync_operating_hours_respects_manual_offline_and_pauses()
    {
        // 1. If manually offline, running sync-operating-hours should keep them offline
        $restaurant = Restaurant::factory()->create([
            'is_accepting_orders' => false,
            'manually_offline' => true,
            'paused_until' => null,
            'is_active' => true,
            'operating_hours' => [
                strtolower(now()->englishDayOfWeek) => [
                    'open' => '00:00',
                    'close' => '23:59',
                ],
            ],
        ]);

        $this->artisan('restaurants:sync-operating-hours')->assertSuccessful();
        $restaurant->refresh();
        $this->assertFalse($restaurant->is_accepting_orders);

        // 2. If paused, running sync-operating-hours should keep them offline
        $restaurant2 = Restaurant::factory()->create([
            'is_accepting_orders' => false,
            'manually_offline' => false,
            'paused_until' => now()->addMinutes(30),
            'is_active' => true,
            'operating_hours' => [
                strtolower(now()->englishDayOfWeek) => [
                    'open' => '00:00',
                    'close' => '23:59',
                ],
            ],
        ]);

        $this->artisan('restaurants:sync-operating-hours')->assertSuccessful();
        $restaurant2->refresh();
        $this->assertFalse($restaurant2->is_accepting_orders);
    }
}
