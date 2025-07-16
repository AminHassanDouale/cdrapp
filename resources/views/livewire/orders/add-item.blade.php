<?php

use App\Actions\Order\AddItemToOrderAction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use function Laravel\Prompts\search;

new class extends Component {
    use Toast;

    public ?Order $order = null;

    public bool $show = false;

    #[Rule('required')]
    public ?int $product_id = null;

    #[Rule('required')]
    public int $quantity = 1;

    public Collection $products;

    public function mount(): void
    {
        $this->search();
    }

    public function save(): void
    {
        $data = $this->validate();

        $add = new AddItemToOrderAction($this->order, $data);
        $add->execute();

        $this->success('Item added.');
        $this->reset(['product_id', 'quantity']);
        $this->dispatch('item-added');
    }

    public function search(string $value = ''): void
    {
        $selectedOption = Product::where('id', $this->product_id)->get();

        $this->products = Product::query()
            ->with('brand')
            ->where('name', 'like', "%$value%")
            ->take(5)
            ->orderBy('name')
            ->get()
            ->merge($selectedOption);
    }

    // Quantities to display on x-select
    public function quantities(): Collection
    {
        $items = collect();

        collect(range(1, 9))->each(fn($item) => $items->add(['id' => $item, 'name' => $item]));

        return $items;
    }

    public function with(): array
    {
        return [
            'quantities' => $this->quantities()
        ];
    }
}; ?>

<div>
    <x-button label="Add" icon="o-plus" @click="$wire.show = true" spinner class="btn-primary" responsive />

    <x-drawer wire:model="show" title="Add item" with-close-button separator right class="w-11/12 lg:w-1/3">
        <x-form wire:submit="save">
            <x-choices
                label="Product"
                wire:model="product_id"
                :options="$products"
                option-sub-label="brand.name"
                option-avatar="cover"
                icon="o-magnifying-glass"
                hint="Search for product name"
                single
                searchable />

            <x-select label="Quantity" wire:model="quantity" :options="$quantities" />

            <x-slot:actions>
                <x-button label="Add" type="submit" spinner="save" icon="o-paper-airplane" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
