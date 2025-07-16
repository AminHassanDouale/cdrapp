<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'status_id' => OrderStatus::where('id', '<>', OrderStatus::DRAFT)->inRandomOrder()->first(),
            'total' => $this->faker->randomFloat(),
            'created_at' => $this->faker->dateTimeBetween('-30 days'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            $order->update(['total' => $order->items()->sum('total')]);
        });
    }
}
