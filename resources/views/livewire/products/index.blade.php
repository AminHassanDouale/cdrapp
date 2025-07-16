<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ClearsProperties;
use App\Traits\ResetsPaginationWhenPropsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, ResetsPaginationWhenPropsChanges, ClearsProperties;

    #[Url]
    public string $name = '';

    #[Url]
    public int $brand_id = 0;

    #[Url]
    public ?int $category_id = 0;

    #[Url]
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public bool $showFilters = false;

    public function filterCount(): int
    {
        return ($this->category_id ? 1 : 0) + ($this->brand_id ? 1 : 0) + (strlen($this->name) ? 1 : 0);
    }

    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['brand', 'category'])
            ->withAggregate('brand', 'name')
            ->withAggregate('category', 'name')
            ->when($this->name, fn(Builder $q) => $q->where('name', 'like', "%$this->name%"))
            ->when($this->brand_id, fn(Builder $q) => $q->where('brand_id', $this->brand_id))
            ->when($this->category_id, fn(Builder $q) => $q->where('category_id', $this->category_id))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(7);
    }

    public function headers(): array
    {
        return [
            ['key' => 'preview', 'label' => '', 'class' => 'w-14', 'sortable' => false],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'brand.name', 'label' => 'Brand', 'sortBy' => 'brand_name', 'class' => 'hidden lg:table-cell'],
            ['key' => 'category.name', 'label' => 'Category', 'sortBy' => 'category_name', 'class' => 'hidden lg:table-cell'],
            ['key' => 'price_human', 'label' => 'Price', 'sortBy' => 'price', 'class' => 'hidden lg:table-cell'],
            ['key' => 'stock', 'label' => 'Stock', 'class' => 'hidden lg:table-cell']
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'products' => $this->products(),
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

<div>
    {{--  HEADER  --}}
    <x-header title="Products" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Name..." wire:model.live.debounce="name" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        {{-- ACTIONS  --}}
        <x-slot:actions>
            <x-button
                label="Filters"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                @click="$wire.showFilters = true"
                class="bg-base-300"
                responsive />

            <x-button label="Create" icon="o-plus" link="/products/create" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{--  TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$products" link="/products/{id}/edit" :sort-by="$sortBy" with-pagination>
            @scope('cell_preview', $product)
            <x-avatar :image="$product->cover" class="!w-10 !rounded-lg" />
            @endscope
        </x-table>
    </x-card>

    {{-- FILTERS --}}
    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            <x-input label="Name ..." wire:model.live.debounce="name" icon="o-user" inline />
            <x-select label="Brand" :options="$brands" wire:model.live="brand_id" icon="o-map-pin" placeholder="All" placeholder-value="0" inline />
            <x-select label="Category" :options="$categories" wire:model.live="category_id" icon="o-flag" placeholder="All" placeholder-value="0" inline />
        </div>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>
</div>
