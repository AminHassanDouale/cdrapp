<?php

use App\Models\Customer;
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
    public string $customer_id = '';

    #[Url]
    public string $user_name = '';

    #[Url]
    public string $public_name = '';

    #[Url]
    public string $customer_type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $trust_level = '';

    #[Url]
    public string $person_id = '';

    #[Url]
    public string $sp_id = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'create_time', 'direction' => 'desc'];

    public bool $showFilters = true;

    public function mount()
    {
        // Set default date range to a broader range that includes historical data
        // Use array format for datepicker component
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subYears(5)->format('Y-m-d'), // 5 years ago
                \Carbon\Carbon::now()->format('Y-m-d') // today
            ];
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function clearFilters()
    {
        $this->reset([
            'customer_id',
            'user_name',
            'public_name',
            'customer_type',
            'status',
            'trust_level',
            'person_id',
            'sp_id',
            'myDate3'
        ]);

        // Reset to a broader date range that includes historical data
        $this->myDate3 = [
            \Carbon\Carbon::now()->subYears(5)->format('Y-m-d'), // 5 years ago
            \Carbon\Carbon::now()->format('Y-m-d') // today
        ];

        $this->resetPage();
    }

    public function filterCount(): int
    {
        return collect([
            $this->customer_id,
            $this->user_name,
            $this->public_name,
            $this->customer_type,
            $this->status,
            $this->trust_level,
            $this->person_id,
            $this->sp_id,
            // Handle array or string for myDate3
            is_array($this->myDate3) ? !empty($this->myDate3) : !empty($this->myDate3)
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function customers(): LengthAwarePaginator
    {
        // Log the current filters for debugging
        \Log::info('Customer filters applied', [
            'customer_id' => $this->customer_id,
            'user_name' => $this->user_name,
            'public_name' => $this->public_name,
            'customer_type' => $this->customer_type,
            'status' => $this->status,
            'trust_level' => $this->trust_level,
            'person_id' => $this->person_id,
            'sp_id' => $this->sp_id,
            'myDate3' => $this->myDate3
        ]);

        $query = Customer::query();

        // Apply filters with logging
        if (!empty($this->customer_id)) {
            $query->where('customer_id', 'like', "%$this->customer_id%");
            \Log::info('Applied customer_id filter', ['value' => $this->customer_id]);
        }

        if (!empty($this->user_name)) {
            $query->where('user_name', 'like', "%$this->user_name%");
            \Log::info('Applied user_name filter', ['value' => $this->user_name]);
        }

        if (!empty($this->public_name)) {
            // Check if public_name column exists and has data
            $query->where(function($q) {
                $q->where('public_name', 'like', "%$this->public_name%")
                  ->orWhere('public_name', 'ilike', "%$this->public_name%"); // Case-insensitive for PostgreSQL
            });
            \Log::info('Applied public_name filter', ['value' => $this->public_name]);
        }

        if (!empty($this->customer_type)) {
            $query->where('customer_type', $this->customer_type);
            \Log::info('Applied customer_type filter', ['value' => $this->customer_type]);
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
            \Log::info('Applied status filter', ['value' => $this->status]);
        }

        if (!empty($this->trust_level)) {
            $query->where('trust_level', $this->trust_level);
            \Log::info('Applied trust_level filter', ['value' => $this->trust_level]);
        }

        if (!empty($this->person_id)) {
            $query->where('person_id', 'like', "%$this->person_id%");
            \Log::info('Applied person_id filter', ['value' => $this->person_id]);
        }

        if (!empty($this->sp_id)) {
            $query->where('sp_id', $this->sp_id);
            \Log::info('Applied sp_id filter', ['value' => $this->sp_id]);
        }

        // Date range filter
        if (!empty($this->myDate3)) {
            try {
                // Handle date range from datepicker (can be array or string)
                if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
                    // Array format from datepicker: ['2024-07-01', '2024-07-11']
                    $startDate = $this->myDate3[0];
                    $endDate = $this->myDate3[1];

                    // Since create_time is text, we need to handle it properly
                    $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                          ->whereRaw("DATE(create_time) <= ?", [$endDate]);

                    \Log::info('Applied date range filter (array)', [
                        'startDate' => $startDate,
                        'endDate' => $endDate
                    ]);

                } elseif (is_string($this->myDate3) && !empty($this->myDate3)) {
                    // String format fallback: "01/07/2024 to 11/07/2024"
                    if (str_contains($this->myDate3, ' to ')) {
                        $dates = explode(' to ', $this->myDate3);
                        if (count($dates) === 2) {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                            $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                                  ->whereRaw("DATE(create_time) <= ?", [$endDate]);

                            \Log::info('Applied date range filter (string)', [
                                'startDate' => $startDate,
                                'endDate' => $endDate
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but don't break the query
                \Log::error('Date range filter error on create_time: ' . $e->getMessage(), [
                    'myDate3' => $this->myDate3,
                    'exception' => $e
                ]);
            }
        }

        // Log the final SQL query for debugging
        $query->orderBy(...array_values($this->sortBy));

        // Get SQL with bindings for debugging
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        \Log::info('Final SQL query', [
            'sql' => $sql,
            'bindings' => $bindings
        ]);

        $results = $query->paginate(10);

        // Log results count
        \Log::info('Query results', [
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage()
        ]);

        return $results;
    }

    public function headers(): array
    {
        return [
            ['key' => 'customer_id', 'label' => 'ID Client'],
            ['key' => 'user_name', 'label' => 'Nom utilisateur'],
            ['key' => 'public_name', 'label' => 'Nom public', 'class' => 'hidden lg:table-cell'],
            ['key' => 'customer_type', 'label' => 'Type', 'class' => 'hidden lg:table-cell'],
            ['key' => 'trust_level', 'label' => 'Confiance', 'class' => 'hidden lg:table-cell'],
            ['key' => 'person_id', 'label' => 'Person ID', 'class' => 'hidden xl:table-cell'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'status'],
            ['key' => 'create_time_formatted', 'label' => 'Date création', 'sortBy' => 'create_time', 'class' => 'hidden lg:table-cell'],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false]
        ];
    }

    public function statusOptions(): array
    {
        return [
            ['id' => 'ACTIVE', 'name' => 'Actif'],
            ['id' => 'INACTIVE', 'name' => 'Inactif'],
            ['id' => 'PENDING', 'name' => 'En attente'],
            ['id' => 'SUSPENDED', 'name' => 'Suspendu'],
            ['id' => 'BLOCKED', 'name' => 'Bloqué'],
            ['id' => 'TERMINATED', 'name' => 'Résilié']
        ];
    }

    public function customerTypeOptions(): array
    {
        return [
            ['id' => 'INDIVIDUAL', 'name' => 'Particulier'],
            ['id' => 'CORPORATE', 'name' => 'Entreprise'],
            ['id' => 'PREMIUM', 'name' => 'Premium'],
            ['id' => 'VIP', 'name' => 'VIP']
        ];
    }

    public function trustLevelOptions(): array
    {
        return [
            ['id' => '0', 'name' => 'Niveau 0 - Non vérifié'],
            ['id' => '1', 'name' => 'Niveau 1 - Basique'],
            ['id' => '2', 'name' => 'Niveau 2 - Intermédiaire'],
            ['id' => '3', 'name' => 'Niveau 3 - Avancé'],
            ['id' => '4', 'name' => 'Niveau 4 - Premium'],
            ['id' => '5', 'name' => 'Niveau 5 - Maximum']
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'customers' => $this->customers(),
            'statusOptions' => $this->statusOptions(),
            'customerTypeOptions' => $this->customerTypeOptions(),
            'trustLevelOptions' => $this->trustLevelOptions(),
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

@php
    $config1 = ['altFormat' => 'd/m/Y'];
    $config2 = ['mode' => 'range'];
@endphp

<div>
    {{--  HEADER  --}}
    <x-header title="Clients" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Nom public du client..." wire:model.live.debounce="public_name" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        {{-- ACTIONS  --}}
        <x-slot:actions>
            <x-button
                label="{{ $showFilters ? 'Masquer filtres' : 'Afficher filtres' }}"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                wire:click="toggleFilters"
                class="bg-base-300"
                responsive />

            <x-button label="Créer" icon="o-plus" link="/customers/create" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="ID Client" wire:model.live.debounce="customer_id" icon="o-identification" />

            <x-input label="Nom utilisateur" wire:model.live.debounce="user_name" icon="o-user" />

            <x-input label="Nom public" wire:model.live.debounce="public_name" icon="o-user-circle" />

            <x-input label="Person ID" wire:model.live.debounce="person_id" icon="o-finger-print" />

            <x-select
                label="Type de client"
                :options="$customerTypeOptions"
                wire:model.live="customer_type"
                icon="o-building-office"
                placeholder="Tous les types"
                placeholder-value="" />

            <x-select
                label="Statut"
                :options="$statusOptions"
                wire:model.live="status"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />

            <x-select
                label="Niveau de confiance"
                :options="$trustLevelOptions"
                wire:model.live="trust_level"
                icon="o-shield-check"
                placeholder="Tous les niveaux"
                placeholder-value="" />

            <x-input label="SP ID" wire:model.live.debounce="sp_id" icon="o-building-storefront" />
        </div>

        <div class="grid grid-cols-1 gap-4 mb-4 lg:grid-cols-2">
            <x-datepicker
                label="Période de création"
                wire:model="myDate3"
                icon="o-calendar"
                :config="$config2"
                inline />

            <div class="flex items-end gap-2">
                <x-button
                    label="Réinitialiser filtres"
                    icon="o-x-mark"
                    wire:click="clearFilters"
                    class="btn-outline"
                    spinner />
            </div>
        </div>
    </x-card>
    @endif

    {{--  TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$customers" :sort-by="$sortBy" with-pagination>
            @scope('cell_status_badge', $customer)
            @php
                $statusColors = [
                    'ACTIVE' => 'badge-success',
                    'INACTIVE' => 'badge-neutral',
                    'PENDING' => 'badge-warning',
                    'SUSPENDED' => 'badge-error',
                    'BLOCKED' => 'badge-error',
                    'TERMINATED' => 'badge-ghost'
                ];
                $statusLabels = [
                    'ACTIVE' => 'Actif',
                    'INACTIVE' => 'Inactif',
                    'PENDING' => 'En attente',
                    'SUSPENDED' => 'Suspendu',
                    'BLOCKED' => 'Bloqué',
                    'TERMINATED' => 'Résilié'
                ];
            @endphp
            <x-badge :value="$statusLabels[$customer->status] ?? $customer->status"
                     :class="$statusColors[$customer->status] ?? 'badge-neutral'" />
            @endscope

            @scope('cell_create_time_formatted', $customer)
            @php
                // Handle text-based date format
                try {
                    if ($customer->create_time) {
                        $date = \Carbon\Carbon::parse($customer->create_time);
                        echo $date->format('d/m/Y H:i');
                    } else {
                        echo '-';
                    }
                } catch (Exception $e) {
                    echo $customer->create_time ?? '-';
                }
            @endphp
            @endscope

            @scope('cell_actions', $customer)
            <div class="flex gap-2">
                <x-button
                    icon="o-eye"
                    link="/customers/{{ $customer->customer_id }}"
                    class="btn-ghost btn-sm"
                    tooltip="Voir détails" />

                <x-button
                    icon="o-identification"
                    link="/customers/{{ $customer->customer_id }}/kyc"
                    class="btn-ghost btn-sm"
                    tooltip="KYC" />

                <x-button
                    icon="o-credit-card"
                    link="/customers/{{ $customer->customer_id }}/transactions"
                    class="btn-ghost btn-sm"
                    tooltip="Transactions" />

                <x-button
                    icon="o-pencil"
                    link="/customers/{{ $customer->customer_id }}/edit"
                    class="btn-ghost btn-sm"
                    tooltip="Modifier" />
            </div>
            @endscope
        </x-table>
    </x-card>
</div>
