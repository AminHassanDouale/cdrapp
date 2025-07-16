<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;

class Spotlight
{
    public function search(Request $request)
    {
        return collect()
            ->merge($this->actions($request->search))
            ->merge($this->orders($request->search))
            ->merge($this->products($request->search))
            ->merge($this->users($request->search));
    }

    // Database search
    public function orders(string $search = ''): Collection
    {
        $icon = Blade::render("<x-icon name='o-gift' class='w-11 h-11 p-2 bg-yellow-50 rounded-full' />");

        return Order::query()
            ->with('user')
            ->where('id', 'like', "%$search%")
            ->take(3)
            ->get()
            ->map(function (Order $order) use ($icon) {
                return [
                    'name' => "Order #{$order->id}",
                    'description' => "{$order->user->name} / $order->date_human / $order->total",
                    'link' => "/orders/$order->id/edit",
                    'icon' => $icon
                ];
            });
    }

    // Database search
    public function products(string $search = ''): Collection
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('name', 'like', "%$search%")
            ->orWhereRelation('category', 'name', 'like', "%$search%")
            ->orWhereRelation('brand', 'name', 'like', "%$search%")
            ->take(3)
            ->get()
            ->map(function (Product $product) {
                return [
                    'name' => $product->name,
                    'description' => "{$product->category->name}, {$product->brand->name}",
                    'link' => "/products/$product->id/edit",
                    'avatar' => $product->cover
                ];
            });
    }

    // Database search
    public function users(string $search = ''): Collection
    {
        return User::query()
            ->where('name', 'like', "%$search%")
            ->take(3)
            ->get()
            ->map(function (User $user) {
                return [
                    'name' => $user->name,
                    'description' => 'Customer',
                    'link' => "/users/$user->id",
                    'avatar' => $user->avatar
                ];
            });
    }

    // Static search, but this could be stored on database for easy management
    public function actions(string $search = ''): Collection
    {
        return collect([
            [
                'name' => 'Dashboard',
                'description' => 'Go to dashboard',
                'link' => "/",
                'icon' => Blade::render("<x-icon name='o-chart-pie' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Categories',
                'description' => 'Manage categories',
                'link' => "/categories",
                'icon' => Blade::render("<x-icon name='o-hashtag' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Brands',
                'description' => 'Manage brands',
                'link' => "/brands",
                'icon' => Blade::render("<x-icon name='o-tag' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Products',
                'description' => 'Manage products',
                'link' => "/products",
                'icon' => Blade::render("<x-icon name='o-cube' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Users',
                'description' => 'Manage users & customers',
                'link' => "/users",
                'icon' => Blade::render("<x-icon name='o-user' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Orders',
                'description' => 'Manage orders',
                'link' => "/orders",
                'icon' => Blade::render("<x-icon name='o-gift' class='w-11 h-11 p-2 bg-primary/20 rounded-full' />")
            ],
            [
                'name' => 'Users',
                'description' => 'Create a new user/customer',
                'link' => "/users/create",
                'icon' => Blade::render("<x-icon name='o-bolt' class='w-11 h-11 p-2 bg-warning/20 rounded-full' />")
            ],
            [
                'name' => 'Order',
                'description' => 'Create a new order',
                'link' => "/orders/create",
                'icon' => Blade::render("<x-icon name='o-bolt' class='w-11 h-11 p-2 bg-warning/20 rounded-full' />")
            ],
            [
                'name' => 'Product',
                'description' => 'Create a new product',
                'link' => "/products/create",
                'icon' => Blade::render("<x-icon name='o-bolt' class='w-11 h-11 p-2 bg-warning/20 rounded-full' />")
            ],
        ])
            ->filter(fn(array $item) => str($item['name'] . $item['description'])->contains($search, true))
            ->take(3);
    }
}
