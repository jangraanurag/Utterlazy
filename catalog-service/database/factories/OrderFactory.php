<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'restaurant_id' => Restaurant::factory(),
            'status' => 'pending',
            'payment_mode' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 100.00,
            'tax_amount' => 5.00,
            'delivery_fee' => 10.00,
            'grand_total' => 115.00,
            'delivery_address_lat' => 12.9715987,
            'delivery_address_lng' => 77.5945627,
        ];
    }
}
