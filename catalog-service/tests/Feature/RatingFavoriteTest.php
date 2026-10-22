<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Restaurant;
use App\Models\RestaurantRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingFavoriteTest extends TestCase
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

    public function test_user_can_rate_restaurant()
    {
        $response = $this->actingAs($this->user)->postJson('/api/ratings', [
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'feedback' => 'Great food!',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('restaurant_ratings', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'rating' => 5,
        ]);
    }

    public function test_user_can_update_rating()
    {
        RestaurantRating::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/ratings', [
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'feedback' => 'Updated review',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('restaurant_ratings', ['rating' => 5]);
    }

    public function test_user_can_get_restaurant_ratings()
    {
        RestaurantRating::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/restaurants/{$this->restaurant->id}/ratings");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_can_toggle_favorite()
    {
        $response = $this->actingAs($this->user)->postJson('/api/favorites/toggle', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_favorite', true);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_user_can_unfavorite()
    {
        Favorite::create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/favorites/toggle', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_favorite', false);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_user_can_list_favorites()
    {
        Favorite::create([
            'user_id' => $this->user->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/favorites');

        $response->assertOk();
        $response->assertJsonCount(1, 'favorites');
    }
}
