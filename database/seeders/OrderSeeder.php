<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Order::count()) {
            return;
        }

        User::all()->each(function (User $user) {
            Order::factory(2)
                ->for($user)
                ->has(OrderItem::factory()->count(2), 'items')
                ->create();
        });
    }
}
