<?php

use App\Models\Country;
use App\Models\Order;
use App\Models\User;
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
    public ?int $country_id = 0;

    #[Url]
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public bool $showFilters = false;

    // Count filters
    public function filterCount(): int
    {
        return ($this->country_id ? 1 : 0) + (strlen($this->name) ? 1 : 0);
    }

    // All countries
    public function countries(): Collection
    {
        return Country::orderBy('name')->get();
    }

    // All users
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['country', 'orders.status'])
            ->withAggregate('country', 'name')
            ->when($this->name, fn(Builder $q) => $q->where('name', 'like', "%$this->name%"))
            ->when($this->country_id, fn(Builder $q) => $q->where('country_id', $this->country_id))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(7);
    }

    public function headers(): array
    {
        return [
            ['key' => 'avatar', 'label' => '', 'class' => 'w-14', 'sortable' => false],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'country.name', 'label' => 'Country', 'sortBy' => 'country_name', 'class' => 'hidden lg:table-cell'],
            ['key' => 'email', 'label' => 'E-mail', 'class' => 'hidden lg:table-cell']
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'users' => $this->users(),
            'countries' => $this->countries(),
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

<div>
    {{--  HEADER  --}}
    <x-header title="Customers" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Name..." wire:model.live.debounce="name" icon="o-magnifying-glass" clearable />
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

            <x-button label="Create" icon="o-plus" link="/users/create" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{--  TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" link="/users/{id}" with-pagination>
            {{-- Avatar scope --}}
            @scope('cell_avatar', $user)
            <x-avatar :image="$user->avatar" class="!w-10" />
            @endscope
        </x-table>
    </x-card>

    {{-- FILTERS --}}
    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            <x-input label="Name ..." wire:model.live.debounce="name" icon="o-user" inline />
            <x-select label="Country" :options="$countries" wire:model.live="country_id" icon="o-flag" placeholder="All" placeholder-value="0" inline />
        </div>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>
</div>
