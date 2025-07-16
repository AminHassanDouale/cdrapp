<?php

use App\Models\Operator;
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
    public string $operator_id = '';

    #[Url]
    public string $operator_code = '';

    #[Url]
    public string $user_name = '';

    #[Url]
    public string $public_name = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $owned_identity_type = '';

    #[Url]
    public string $owned_identity_id = '';

    #[Url]
    public string $sp_id = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'operator_id', 'direction' => 'desc'];

    public bool $showFilters = true;

    public function mount()
    {
        // Set default date range to recent data for performance
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subYears(2)->format('Y-m-d'),
                \Carbon\Carbon::now()->format('Y-m-d')
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
            'operator_id',
            'operator_code',
            'user_name',
            'public_name',
            'status',
            'owned_identity_type',
            'owned_identity_id',
            'sp_id',
            'myDate3'
        ]);

        // Reset to a narrower date range for performance
        $this->myDate3 = [
            \Carbon\Carbon::now()->subYears(2)->format('Y-m-d'),
            \Carbon\Carbon::now()->format('Y-m-d')
        ];

        $this->resetPage();
    }

    public function filterCount(): int
    {
        return collect([
            $this->operator_id,
            $this->operator_code,
            $this->user_name,
            $this->public_name,
            $this->status,
            $this->owned_identity_type,
            $this->owned_identity_id,
            $this->sp_id,
            is_array($this->myDate3) ? !empty($this->myDate3) : !empty($this->myDate3)
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function operators(): LengthAwarePaginator
    {
        \Log::info('Operator filters applied', [
            'operator_id' => $this->operator_id,
            'operator_code' => $this->operator_code,
            'user_name' => $this->user_name,
            'public_name' => $this->public_name,
            'status' => $this->status,
            'owned_identity_type' => $this->owned_identity_type,
            'owned_identity_id' => $this->owned_identity_id,
            'sp_id' => $this->sp_id,
            'myDate3' => $this->myDate3
        ]);

        $query = Operator::query()
            ->select([
                'operator_id',
                'operator_code',
                'user_name',
                'public_name',
                'status',
                'owned_identity_type',
                'owned_identity_id',
                'sp_id',
                'active_time',
                'create_time'
            ]);

        // Apply filters early to reduce dataset size
        if (!empty($this->operator_id)) {
            $query->where('operator_id', 'like', "%$this->operator_id%");
        }

        if (!empty($this->operator_code)) {
            $query->where('operator_code', 'like', "%$this->operator_code%");
        }

        if (!empty($this->user_name)) {
            $query->where('user_name', 'like', "%$this->user_name%");
        }

        if (!empty($this->public_name)) {
            $query->where(function($q) {
                $q->where('public_name', 'like', "%$this->public_name%")
                  ->orWhere('public_name', 'ilike', "%$this->public_name%");
            });
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        if (!empty($this->owned_identity_type)) {
            $query->where('owned_identity_type', $this->owned_identity_type);
        }

        if (!empty($this->owned_identity_id)) {
            $query->where('owned_identity_id', 'like', "%$this->owned_identity_id%");
        }

        if (!empty($this->sp_id)) {
            $query->where('sp_id', $this->sp_id);
        }

        // Date range filter - most selective filter, apply early
        if (!empty($this->myDate3)) {
            try {
                if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
                    $startDate = $this->myDate3[0];
                    $endDate = $this->myDate3[1];

                    $query->whereRaw("DATE(active_time) >= ?", [$startDate])
                          ->whereRaw("DATE(active_time) <= ?", [$endDate]);

                } elseif (is_string($this->myDate3) && !empty($this->myDate3)) {
                    if (str_contains($this->myDate3, ' to ')) {
                        $dates = explode(' to ', $this->myDate3);
                        if (count($dates) === 2) {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                            $query->whereRaw("DATE(active_time) >= ?", [$startDate])
                                  ->whereRaw("DATE(active_time) <= ?", [$endDate]);
                        }
                    }
                }
            } catch (Exception $e) {
                \Log::error('Date range filter error: ' . $e->getMessage(), [
                    'myDate3' => $this->myDate3,
                    'exception' => $e
                ]);
            }
        }

        // Default date filter if no filters are applied to limit dataset
        $hasFilters = !empty($this->operator_id) || !empty($this->operator_code) ||
                     !empty($this->user_name) || !empty($this->public_name) ||
                     !empty($this->status) || !empty($this->owned_identity_type) ||
                     !empty($this->owned_identity_id) || !empty($this->sp_id) ||
                     !empty($this->myDate3);

        if (!$hasFilters) {
            // If no filters, limit to recent records (last 2 years) for performance
            $defaultStartDate = \Carbon\Carbon::now()->subYears(2)->format('Y-m-d');
            $query->whereRaw("DATE(active_time) >= ?", [$defaultStartDate]);
        }

        $query->orderBy(...array_values($this->sortBy));

        // Use regular pagination but with larger page size for better performance
        $results = $query->paginate(50);

        \Log::info('Operator query results', [
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'last_page' => $results->lastPage()
        ]);

        return $results;
    }

    public function headers(): array
    {
        return [
            ['key' => 'operator_id', 'label' => 'ID Opérateur'],
            ['key' => 'operator_code', 'label' => 'Code', 'class' => 'hidden lg:table-cell'],
            ['key' => 'user_name', 'label' => 'Nom utilisateur'],
            ['key' => 'public_name', 'label' => 'Nom public', 'class' => 'hidden lg:table-cell'],
            ['key' => 'identity_type_name', 'label' => 'Type propriétaire', 'class' => 'hidden xl:table-cell', 'sortBy' => 'owned_identity_type'],
            ['key' => 'owned_identity_id', 'label' => 'ID Propriétaire', 'class' => 'hidden xl:table-cell'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'status'],
            ['key' => 'active_time_formatted', 'label' => 'Date activation', 'sortBy' => 'active_time', 'class' => 'hidden lg:table-cell'],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false]
        ];
    }

    public function statusOptions(): array
    {
        return [
            ['id' => '01', 'name' => 'Inactif'],
            ['id' => '03', 'name' => 'Actif'],
            ['id' => '05', 'name' => 'Suspendu'],
            ['id' => '07', 'name' => 'Bloqué'],
            ['id' => '09', 'name' => 'Fermé']
        ];
    }

    public function identityTypeOptions(): array
    {
        return [
            ['id' => '1', 'name' => 'Client'],
            ['id' => '2', 'name' => 'Organisation'],
            ['id' => '3', 'name' => 'Opérateur']
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'operators' => $this->operators(),
            'statusOptions' => $this->statusOptions(),
            'identityTypeOptions' => $this->identityTypeOptions(),
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
    <x-header title="Opérateurs" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Nom utilisateur..." wire:model.live.debounce="user_name" icon="o-magnifying-glass" clearable />
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

            <x-button label="Créer" icon="o-plus" link="/operators/create" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="ID Opérateur" wire:model.live.debounce="operator_id" icon="o-identification" />

            <x-input label="Code opérateur" wire:model.live.debounce="operator_code" icon="o-hashtag" />

            <x-input label="Nom utilisateur" wire:model.live.debounce="user_name" icon="o-user" />

            <x-input label="Nom public" wire:model.live.debounce="public_name" icon="o-user-circle" />

            <x-input label="ID Propriétaire" wire:model.live.debounce="owned_identity_id" icon="o-finger-print" />

            <x-select
                label="Type de propriétaire"
                :options="$identityTypeOptions"
                wire:model.live="owned_identity_type"
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

            <x-input label="SP ID" wire:model.live.debounce="sp_id" icon="o-building-storefront" />
        </div>

        <div class="grid grid-cols-1 gap-4 mb-4 lg:grid-cols-2">
            <x-datepicker
                label="Période d'activation"
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
        <x-table :headers="$headers" :rows="$operators" :sort-by="$sortBy" with-pagination>
            @scope('cell_identity_type_name', $operator)
            @php
                $typeLabels = [
                    1 => 'Client',
                    2 => 'Organisation',
                    3 => 'Opérateur'
                ];
            @endphp
            <x-badge value="{{ $typeLabels[$operator->owned_identity_type] ?? 'Inconnu' }}" class="badge-neutral" />
            @endscope

            @scope('cell_status_badge', $operator)
            @php
                $statusColors = [
                    '01' => 'badge-neutral',
                    '03' => 'badge-success',
                    '05' => 'badge-warning',
                    '07' => 'badge-error',
                    '09' => 'badge-ghost'
                ];
                $statusLabels = [
                    '01' => 'Inactif',
                    '03' => 'Actif',
                    '05' => 'Suspendu',
                    '07' => 'Bloqué',
                    '09' => 'Fermé'
                ];
            @endphp
            <x-badge :value="$statusLabels[$operator->status] ?? $operator->status"
                     :class="$statusColors[$operator->status] ?? 'badge-neutral'" />
            @endscope

            @scope('cell_active_time_formatted', $operator)
            @php
                try {
                    if ($operator->active_time) {
                        $date = \Carbon\Carbon::parse($operator->active_time);
                        echo $date->format('d/m/Y H:i');
                    } else {
                        echo '-';
                    }
                } catch (Exception $e) {
                    echo $operator->active_time ?? '-';
                }
            @endphp
            @endscope

            @scope('cell_actions', $operator)
            <div class="flex gap-2">
                <x-button
                    icon="o-eye"
                    link="/operators/{{ $operator->operator_id }}"
                    class="btn-ghost btn-sm"
                    tooltip="Voir détails" />

                <x-button
                    icon="o-identification"
                    link="/operators/{{ $operator->operator_id }}/kyc"
                    class="btn-ghost btn-sm"
                    tooltip="KYC" />

                <x-button
                    icon="o-eye"
                    link="/operators/{{ $operator->operator_id }}/activity"
                    class="btn-ghost btn-sm"
                    tooltip="Activité" />

                <x-button
                    icon="o-pencil"
                    link="/operators/{{ $operator->operator_id }}/edit"
                    class="btn-ghost btn-sm"
                    tooltip="Modifier" />
            </div>
            @endscope
        </x-table>
    </x-card>
</div>
