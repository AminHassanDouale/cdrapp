<?php

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Traits\ClearsProperties;
use App\Traits\ResetsPaginationWhenPropsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, ResetsPaginationWhenPropsChanges, ClearsProperties;

    #[Url]
    public int $status_id = 0;

    #[Url]
    public ?int $country_id = 0;

    #[Url]
    public string $name = '';

    #[Url]
    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public bool $showFilters = false;

    // Count filter types
    public function filterCount(): int
    {
        return ($this->status_id ? 1 : 0) + ($this->country_id ? 1 : 0) + (strlen($this->name) ? 1 : 0);
    }

    // All status
    public function statuses(): Collection
    {
        return OrderStatus::where('id', '<>', OrderStatus::DRAFT)->get();
    }

    // All countries
    public function countries(): Collection
    {
        return Country::orderBy('name')->get();
    }

    /**
     * Table query.
     *
     * These withAggregate() adds new columns on Collection making ease to sort it and is used by headers().
     *  Ex: `user_name`, `status_name`
     */
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->withAggregate('user', 'name')
            ->withAggregate('status', 'name')
            ->withAggregate('country as country_name', 'countries.name')
            ->when($this->status_id, fn(Builder $q) => $q->where('status_id', $this->status_id))
            ->when($this->country_id, fn(Builder $q) => $q->whereRelation('user', 'country_id', $this->country_id))
            ->when($this->name, fn(Builder $q) => $q->whereRelation('user', 'name', 'like', "%$this->name%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'hidden lg:table-cell'],
            ['key' => 'date_human', 'label' => 'Date', 'sortBy' => 'created_at', 'class' => 'hidden lg:table-cell'],
            ['key' => 'user.name', 'label' => 'Customer', 'sortBy' => 'user_name'],
            ['key' => 'user.country.name', 'label' => 'Country', 'sortBy' => 'country_name', 'class' => 'hidden lg:table-cell'],
            ['key' => 'total_human', 'label' => 'Total', 'sortBy' => 'total'],
            ['key' => 'status', 'label' => 'Status', 'sortBy' => 'status_name', 'class' => 'hidden lg:table-cell']
        ];
    }

    public function with(): array
    {
        return [
            'orders' => $this->orders(),
            'headers' => $this->headers(),
            'statuses' => $this->statuses(),
            'countries' => $this->countries(),
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Orders" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Customer..." wire:model.live.debounce="name" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        {{-- ACTIONS  --}}
        <x-slot:actions>
            <x-button label="Filters"
                      icon="o-funnel"
                      :badge="$filterCount"
                      badge-classes="font-mono"
                      @click="$wire.showFilters = true"
                      class="bg-base-300"
                      responsive />

            <x-button label="Create" link="/orders/create" icon="o-plus" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- TABLE --}}
    <x-card shadow>
        <x-table :headers="$headers" :rows="$orders" link="/orders/{id}/edit" with-pagination :sort-by="$sortBy">
            @scope('cell_status', $order)
            <x-badge :value="$order->status->name" :class="$order->status->color" />
            @endscope
        </x-table>
    </x-card>

    {{-- FILTERS --}}
    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            <x-input label="Customer ..." wire:model.live.debounce="name" icon="o-user" inline />
            <x-select label="Status" :options="$statuses" wire:model.live="status_id" icon="o-map-pin" placeholder="All" placeholder-value="0" inline />
            <x-select label="Country" :options="$countries" wire:model.live="country_id" icon="o-flag" placeholder="All" placeholder-value="0" inline />
        </div>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>
</div>
