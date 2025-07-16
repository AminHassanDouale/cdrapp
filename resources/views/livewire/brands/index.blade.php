<?php

use App\Actions\DeleteBrandAction;
use App\Exceptions\AppException;
use App\Models\Brand;
use App\Traits\ResetsPaginationWhenPropsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination, ResetsPaginationWhenPropsChanges;

    #[Url]
    public string $search = '';

    #[Url]
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // Selected Brand to edit on modal
    public ?Brand $brand;

    #[On('brand-saved')]
    #[On('brand-cancel')]
    public function clear(): void
    {
        $this->reset();
    }

    public function edit(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function delete(Brand $brand): void
    {
        $delete = new DeleteBrandAction($brand);
        $delete->execute();

        $this->success('Brand deleted.');
    }

    public function brands(): LengthAwarePaginator
    {
        return Brand::query()
            ->withCount('products')
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(9);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-20'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'products_count', 'label' => 'Products', 'class' => 'w-32', 'sortBy' => 'products_count'],
            ['key' => 'date_human', 'label' => 'Created at', 'class' => 'hidden lg:table-cell']
        ];
    }

    public function with(): array
    {
        return [
            'brands' => $this->brands(),
            'headers' => $this->headers()
        ];
    }
}; ?>

<div>
    {{--  HEADER  --}}
    <x-header title="Brands" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <livewire:brands.create />
        </x-slot:actions>
    </x-header>

    {{--  TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$brands" @row-click="$wire.edit($event.detail.id)" :sort-by="$sortBy" with-pagination>
            @scope('actions', $brand)
            <x-button wire:click="delete({{ $brand->id }})" icon="o-trash" class="btn-sm btn-ghost text-error" wire:confirm="Are you sure?" spinner />
            @endscope
        </x-table>
    </x-card>

    {{--   EIDT MODAL --}}
    <livewire:brands.edit wire:model="brand" />
</div>
