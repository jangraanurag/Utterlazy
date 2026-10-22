<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'phone' => '1234567890',
            'latitude' => 12.9715987,
            'longitude' => 77.5945627,
            'address' => $this->faker->address(),
            'is_active' => true,
            'is_accepting_orders' => true,
            'operating_hours' => ['open' => '11:00', 'close' => '23:00'],
        ];
    }
}
