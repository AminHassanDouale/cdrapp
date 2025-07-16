<?php

use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component {
    #[Reactive]
    public string $period = '-30 days';

    public function bestSellers(): Collection
    {
        return OrderItem::query()
            ->with('product.category')
            ->selectRaw("count(1) as amount, product_id")
            ->whereRelation('order', 'created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->groupBy('product_id')
            ->orderByDesc('amount')
            ->take(3)
            ->get()
            ->transform(function (OrderItem $item) {
                $product = $item->product;
                $product->amount = $item->amount;

                return $product;
            });
    }

    public function with(): array
    {
        return [
            'bestSellers' => $this->bestSellers(),
        ];
    }
}; ?>

<div>
    <x-card title="Best sellers" separator shadow>
        <x-slot:menu>
            <x-button label="Products" icon-right="o-arrow-right" link="/products" class="btn-ghost btn-sm" />
        </x-slot:menu>

        @foreach($bestSellers as $product)
            <x-list-item :item="$product" sub-value="category.name" avatar="cover" link="/products/{{ $product->id }}/edit" no-separator>
                <x-slot:actions>
                    <x-badge :value="$product->amount" class="font-bold" />
                </x-slot:actions>
            </x-list-item>
        @endforeach
    </x-card>
</div>
