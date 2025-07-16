<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::inRandomOrder()->first(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'price' => function (array $attributes) {
                return Product::find($attributes['product_id'])->price;
            },
            'total' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['price'];
            }
        ];
    }
}
