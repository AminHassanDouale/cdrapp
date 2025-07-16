<?php

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component {
    #[Reactive]
    public string $period = '-30 days';

    // General statistics
    public function stats(): array
    {
        $newCustomers = User::query()
            ->where('created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->count();

        $orders = Order::query()
            ->where('created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->count();

        $gross = Order::query()
            ->where('created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->sum('total');

        return [
            'newCustomers' => $newCustomers,
            'orders' => $orders,
            'gross' => Number::currency($gross)
        ];
    }

    public function with(): array
    {
        return [
            'stats' => $this->stats(),
        ];
    }
}; ?>

<div>
    <div class="grid gap-5 lg:grid-cols-4 lg:gap-8">
        <x-stat :value="$stats['gross']" title="Gross" icon="o-banknotes" class="truncate shadow text-ellipsis" />
        <x-stat :value="$stats['orders']" title="Orders" icon="o-gift" class="shadow" />
        <x-stat :value="$stats['newCustomers']" title="New customers" icon="o-user-plus" class="shadow" />
        <x-stat value="maryUI" title="Built with" icon="o-heart" color="!text-pink-500" class="shadow" />
    </div>
</div>
