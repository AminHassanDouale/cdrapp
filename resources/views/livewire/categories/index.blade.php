<?php

use App\Actions\DeleteCategoryAction;
use App\Exceptions\AppException;
use App\Models\Category;
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

    // Selected Category to edit on modal
    public ?Category $category;

    #[On('category-saved')]
    #[On('category-cancel')]
    public function clear(): void
    {
        $this->reset();
    }

    public function edit(Category $category): void
    {
        $this->category = $category;
    }

    public function delete(Category $category): void
    {
        $delete = new DeleteCategoryAction($category);
        $delete->execute();

        $this->success('Category deleted.');
    }

    public function categories(): LengthAwarePaginator
    {
        return Category::query()
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
            'categories' => $this->categories(),
            'headers' => $this->headers()
        ];
    }
}; ?>

<div>
    {{--  HEADER  --}}
    <x-header title="Categories" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <livewire:categories.create />
        </x-slot:actions>
    </x-header>

    {{-- TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$categories" @row-click="$wire.edit($event.detail.id)" :sort-by="$sortBy" with-pagination>
            @scope('actions', $category)
            <x-button wire:click="delete({{ $category->id }})" icon="o-trash" class="btn-sm btn-ghost text-error" wire:confirm="Are you sure?" spinner />
            @endscope
        </x-table>
    </x-card>

    {{-- EDIT MODAL --}}
    <livewire:categories.edit wire:model="category" />
</div>
