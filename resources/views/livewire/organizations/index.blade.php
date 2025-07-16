<?php

use App\Models\Organization;
use App\Traits\ClearsProperties;
use App\Traits\ResetsPaginationWhenPropsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, ResetsPaginationWhenPropsChanges, ClearsProperties;

    #[Url]
    public string $biz_org_id = '';

    #[Url]
    public string $biz_org_name = '';

    #[Url]
    public string $public_name = '';

    #[Url]
    public string $organization_type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $trust_level = '';

    #[Url]
    public string $person_id = '';

    #[Url]
    public string $sp_id = '';

    #[Url]
    public string $organization_code = '';

    #[Url]
    public string $short_code = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'create_time', 'direction' => 'desc'];

    public bool $showFilters = true;

    public function mount()
    {
        // Set default date range to recent data for performance
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subYears(2)->format('Y-m-d'), // 2 years ago instead of 5
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
            'biz_org_id',
            'biz_org_name',
            'public_name',
            'organization_type',
            'status',
            'trust_level',
            'person_id',
            'sp_id',
            'organization_code',
            'short_code',
            'myDate3'
        ]);

        // Reset to a narrower date range for performance
        $this->myDate3 = [
            \Carbon\Carbon::now()->subYears(2)->format('Y-m-d'), // 2 years ago
            \Carbon\Carbon::now()->format('Y-m-d') // today
        ];

        $this->resetPage();
    }

    public function filterCount(): int
    {
        return collect([
            $this->biz_org_id,
            $this->biz_org_name,
            $this->public_name,
            $this->organization_type,
            $this->status,
            $this->trust_level,
            $this->person_id,
            $this->sp_id,
            $this->organization_code,
            $this->short_code,
            is_array($this->myDate3) ? !empty($this->myDate3) : !empty($this->myDate3)
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function organizations(): LengthAwarePaginator|Paginator
    {
        \Log::info('Organization filters applied', [
            'biz_org_id' => $this->biz_org_id,
            'biz_org_name' => $this->biz_org_name,
            'public_name' => $this->public_name,
            'organization_type' => $this->organization_type,
            'status' => $this->status,
            'trust_level' => $this->trust_level,
            'person_id' => $this->person_id,
            'sp_id' => $this->sp_id,
            'organization_code' => $this->organization_code,
            'short_code' => $this->short_code,
            'myDate3' => $this->myDate3
        ]);

        $query = Organization::query()
            ->select([
                'biz_org_id',
                'biz_org_name',
                'public_name',
                'organization_type',
                'trust_level',
                'short_code',
                'status',
                'create_time'
            ])
            ->withCount(['operators as operators_count']);

        // Apply filters early to reduce dataset size
        if (!empty($this->biz_org_id)) {
            $query->where('biz_org_id', 'like', "%$this->biz_org_id%");
        }

        if (!empty($this->biz_org_name)) {
            $query->where('biz_org_name', 'like', "%$this->biz_org_name%");
        }

        if (!empty($this->public_name)) {
            $query->where(function($q) {
                $q->where('public_name', 'like', "%$this->public_name%")
                  ->orWhere('public_name', 'ilike', "%$this->public_name%");
            });
        }

        if (!empty($this->organization_type)) {
            $query->where('organization_type', $this->organization_type);
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        if (!empty($this->trust_level)) {
            $query->where('trust_level', $this->trust_level);
        }

        if (!empty($this->person_id)) {
            $query->where('person_id', 'like', "%$this->person_id%");
        }

        if (!empty($this->sp_id)) {
            $query->where('sp_id', $this->sp_id);
        }

        if (!empty($this->organization_code)) {
            $query->where('organization_code', 'like', "%$this->organization_code%");
        }

        if (!empty($this->short_code)) {
            $query->where('short_code', 'like', "%$this->short_code%");
        }

        // Date range filter - most selective filter, apply early
        if (!empty($this->myDate3)) {
            try {
                if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
                    $startDate = $this->myDate3[0];
                    $endDate = $this->myDate3[1];

                    $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                          ->whereRaw("DATE(create_time) <= ?", [$endDate]);

                } elseif (is_string($this->myDate3) && !empty($this->myDate3)) {
                    if (str_contains($this->myDate3, ' to ')) {
                        $dates = explode(' to ', $this->myDate3);
                        if (count($dates) === 2) {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                            $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                                  ->whereRaw("DATE(create_time) <= ?", [$endDate]);
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
        $hasFilters = !empty($this->biz_org_id) || !empty($this->biz_org_name) ||
                     !empty($this->public_name) || !empty($this->organization_type) ||
                     !empty($this->status) || !empty($this->trust_level) ||
                     !empty($this->person_id) || !empty($this->sp_id) ||
                     !empty($this->organization_code) || !empty($this->short_code) ||
                     !empty($this->myDate3);

        if (!$hasFilters) {
            // If no filters, limit to recent records (last 2 years) for performance
            $defaultStartDate = \Carbon\Carbon::now()->subYears(2)->format('Y-m-d');
            $query->whereRaw("DATE(create_time) >= ?", [$defaultStartDate]);
        }

        $query->orderBy(...array_values($this->sortBy));

        // Use simple pagination for better performance with large datasets
        $results = $query->simplePaginate(25);

        \Log::info('Organization query results', [
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'has_more_pages' => $results->hasMorePages()
        ]);

        return $results;
    }

    public function headers(): array
    {
        return [
            ['key' => 'biz_org_id', 'label' => 'ID Organisation'],
            ['key' => 'biz_org_name', 'label' => 'Nom organisation'],
            ['key' => 'public_name', 'label' => 'Nom public', 'class' => 'hidden lg:table-cell'],
            ['key' => 'organization_type', 'label' => 'Type', 'class' => 'hidden lg:table-cell'],
            ['key' => 'trust_level', 'label' => 'Confiance', 'class' => 'hidden lg:table-cell'],
            ['key' => 'short_code', 'label' => 'Code court', 'class' => 'hidden xl:table-cell'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'status'],
            ['key' => 'create_time_formatted', 'label' => 'Date création', 'sortBy' => 'create_time', 'class' => 'hidden lg:table-cell'],
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

    public function organizationTypeOptions(): array
    {
        return [
            ['id' => 'CORP', 'name' => 'Entreprise'],
            ['id' => 'NGO', 'name' => 'ONG'],
            ['id' => 'GOVT', 'name' => 'Gouvernement'],
            ['id' => 'BANK', 'name' => 'Banque'],
            ['id' => 'RETAIL', 'name' => 'Commerce'],
            ['id' => 'OTHER', 'name' => 'Autre']
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
            'organizations' => $this->organizations(),
            'statusOptions' => $this->statusOptions(),
            'organizationTypeOptions' => $this->organizationTypeOptions(),
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
    <x-header title="Organisations" separator progress-indicator>
        {{--  SEARCH --}}
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Nom public de l'organisation..." wire:model.live.debounce="public_name" icon="o-magnifying-glass" clearable />
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

            <x-button label="Créer" icon="o-plus" link="/organizations/create" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="ID Organisation" wire:model.live.debounce="biz_org_id" icon="o-identification" />

            <x-input label="Nom organisation" wire:model.live.debounce="biz_org_name" icon="o-building-office" />

            <x-input label="Nom public" wire:model.live.debounce="public_name" icon="o-user-circle" />

            <x-input label="Code organisation" wire:model.live.debounce="organization_code" icon="o-hashtag" />

            <x-input label="Code court" wire:model.live.debounce="short_code" icon="o-hashtag" />

            <x-input label="Person ID" wire:model.live.debounce="person_id" icon="o-finger-print" />

            <x-select
                label="Type d'organisation"
                :options="$organizationTypeOptions"
                wire:model.live="organization_type"
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
        <x-table :headers="$headers" :rows="$organizations" :sort-by="$sortBy" with-pagination>
            @scope('cell_status_badge', $organization)
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
            <x-badge :value="$statusLabels[$organization->status] ?? $organization->status"
                     :class="$statusColors[$organization->status] ?? 'badge-neutral'" />
            @endscope

            @scope('cell_create_time_formatted', $organization)
            @php
                try {
                    if ($organization->create_time) {
                        $date = \Carbon\Carbon::parse($organization->create_time);
                        echo $date->format('d/m/Y H:i');
                    } else {
                        echo '-';
                    }
                } catch (Exception $e) {
                    echo $organization->create_time ?? '-';
                }
            @endphp
            @endscope

            @scope('cell_actions', $organization)
            <div class="flex gap-2">
                <x-button
                    icon="o-eye"
                    link="/organizations/{{ $organization->biz_org_id }}"
                    class="btn-ghost btn-sm"
                    tooltip="Voir détails" />

                <x-button
                    icon="o-identification"
                    link="/organizations/{{ $organization->biz_org_id }}/kyc"
                    class="btn-ghost btn-sm"
                    tooltip="KYC" />

                <x-button
                    icon="o-banknotes"
                    link="/organizations/{{ $organization->biz_org_id }}/accounts"
                    class="btn-ghost btn-sm"
                    tooltip="Comptes" />

                <x-button
                    icon="o-users"
                    link="/organizations/{{ $organization->biz_org_id }}/operators"
                    class="btn-ghost btn-sm"
                    tooltip="Opérateurs" />

                <x-button
                    icon="o-pencil"
                    link="/organizations/{{ $organization->biz_org_id }}/edit"
                    class="btn-ghost btn-sm"
                    tooltip="Modifier" />
            </div>
            @endscope
        </x-table>
    </x-card>
</div>
