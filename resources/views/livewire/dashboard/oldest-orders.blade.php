<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component {
    #[Reactive]
    public string $period = '-30 days';

    public bool $show = false;

    // Selected order to preview
    public Order $order;

    // Triggers the order preview drawer
    public function preview(Order $order): void
    {
        $this->show = true;
        $this->order = $order;
    }

    public function orders(): Collection
    {
        return Order::with(['user', 'status'])
            ->where('created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->oldest('id')
            ->take(5)
            ->get();
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'py-4', 'class' => 'hidden lg:table-cell'],
            ['key' => 'date_human', 'label' => 'Date', 'class' => 'hidden lg:table-cell'],
            ['key' => 'user.name', 'label' => 'Customer'],
            ['key' => 'total_human', 'label' => 'Total'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'hidden lg:table-cell']
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'orders' => $this->orders()
        ];
    }
}; ?>

<div>
    <x-card title="Oldest orders" separator shadow progress-indicator class="mt-10">
        <x-slot:menu>
            <x-button label="Orders" icon-right="o-arrow-right" link="/orders" class="btn-ghost btn-sm" />
        </x-slot:menu>
        <x-table :headers="$headers" :rows="$orders" @row-click="$wire.preview($event.detail.id)">
            @scope('cell_status', $order)
            <x-badge :value="$order->status->name" :class="$order->status->color" />
            @endscope
        </x-table>

        @if(!$orders->count())
            <x-icon name="o-list-bullet" label="Nothing here." class="text-gray-400 mt-5" />
        @endif
    </x-card>

    {{-- PREVIEW ORDER --}}
    <x-drawer wire:model="show" title="Order #{{ $order?->id }}" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-button label="Go to order" icon="o-arrow-right" link="/orders/{{ $order?->id }}/edit" class="btn-primary btn-sm" />

        <div class="font-black my-3 mt-10">Customer</div>
        <hr class="mb-3" />

        <x-list-item :item="$order?->user ?? []" sub-value="country.name" no-separator link="/users/{{ $order?->user_id }}" />

        <div class="font-black my-3">Items</div>
        <hr class="mb-3" />

        @foreach($order?->items ?? [] as $item)
            <x-list-item :item="$item" value="product.name" sub-value="product.price_human" avatar="product.cover" no-hover no-separator>
                <x-slot:actions>
                    <x-badge :value="$item->quantity" class="badge-neutral" />
                </x-slot:actions>
            </x-list-item>
        @endforeach

        <hr class="my-5" />

        <div class="font-bold text-right">{{ $order?->total_human }}</div>
    </x-drawer>
</div>
