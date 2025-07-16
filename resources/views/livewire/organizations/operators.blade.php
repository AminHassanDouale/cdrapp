<?php

use App\Models\Organization;
use App\Models\Operator;
use App\Traits\ClearsProperties;
use App\Traits\ResetsPaginationWhenPropsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, ResetsPaginationWhenPropsChanges, ClearsProperties;

    public Organization $organization;

    #[Url]
    public string $operator_id = '';

    #[Url]
    public string $operator_code = '';

    #[Url]
    public string $user_name = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sp_id = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'operator_id', 'direction' => 'asc'];

    public bool $showFilters = false;
    public bool $showDebugInfo = false;

    public function mount(Organization $organization)
    {
        $this->organization = $organization;

        // Set a much wider default date range to catch all operators
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subYears(10)->format('Y-m-d'), // 10 years back
                \Carbon\Carbon::now()->addYear()->format('Y-m-d')     // 1 year forward
            ];
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function toggleDebugInfo()
    {
        $this->showDebugInfo = !$this->showDebugInfo;
    }

    public function clearFilters()
    {
        $this->reset([
            'operator_id',
            'operator_code',
            'user_name',
            'status',
            'sp_id',
            'myDate3'
        ]);

        // Reset to wide date range
        $this->myDate3 = [
            \Carbon\Carbon::now()->subYears(10)->format('Y-m-d'),
            \Carbon\Carbon::now()->addYear()->format('Y-m-d')
        ];

        $this->resetPage();
    }

    public function removeAllFilters()
    {
        // Remove all filters including date range
        $this->reset([
            'operator_id',
            'operator_code',
            'user_name',
            'status',
            'sp_id',
            'myDate3'
        ]);

        $this->myDate3 = []; // Empty date range = no date filtering
        $this->resetPage();
    }

    public function filterCount(): int
    {
        $activeFilters = collect([
            $this->operator_id,
            $this->operator_code,
            $this->user_name,
            $this->status,
            $this->sp_id,
        ])->filter(fn($value) => !empty($value))->count();

        // Only count date filter if it's different from the wide default range
        $defaultStart = \Carbon\Carbon::now()->subYears(10)->format('Y-m-d');
        $defaultEnd = \Carbon\Carbon::now()->addYear()->format('Y-m-d');

        if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
            if ($this->myDate3[0] !== $defaultStart || $this->myDate3[1] !== $defaultEnd) {
                $activeFilters++;
            }
        } elseif (!empty($this->myDate3)) {
            $activeFilters++;
        }

        return $activeFilters;
    }

    public function getDebugInfo(): array
    {
        // Debug information to help troubleshoot
        $baseQuery = Operator::where('owned_identity_id', $this->organization->biz_org_id)
            ->where('owned_identity_type', 5000);

        // Get sample active_time values to debug date issues
        $sampleWithDates = $baseQuery->limit(5)->get(['operator_id', 'user_name', 'status', 'active_time', 'owned_identity_type']);

        return [
            'organization_id' => $this->organization->biz_org_id,
            'total_operators_all' => Operator::count(),
            'total_operators_for_org' => $baseQuery->count(),
            'total_operators_without_date_filter' => Operator::where('owned_identity_id', $this->organization->biz_org_id)
                ->where('owned_identity_type', 5000)->count(),
            'operators_by_identity_type' => Operator::selectRaw('owned_identity_type, count(*) as count')
                ->where('owned_identity_id', $this->organization->biz_org_id)
                ->groupBy('owned_identity_type')
                ->get(),
            'sample_operators_with_dates' => $sampleWithDates,
            'current_filters' => [
                'operator_id' => $this->operator_id,
                'status' => $this->status,
                'date_range' => $this->myDate3,
            ],
            'sql_query' => $this->buildOperatorsQuery()->toSql(),
            'sql_bindings' => $this->buildOperatorsQuery()->getBindings(),
            'query_without_date' => $this->buildOperatorsQueryWithoutDate()->toSql(),
        ];
    }

    private function buildOperatorsQueryWithoutDate(): Builder
    {
        $query = Operator::where('owned_identity_id', $this->organization->biz_org_id)
            ->where('owned_identity_type', 5000);

        // Apply all filters except date
        if (!empty($this->operator_id)) {
            $query->where('operator_id', 'like', "%$this->operator_id%");
        }

        if (!empty($this->operator_code)) {
            $query->where('operator_code', 'like', "%$this->operator_code%");
        }

        if (!empty($this->user_name)) {
            $query->where('user_name', 'like', "%$this->user_name%");
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        if (!empty($this->sp_id)) {
            $query->where('sp_id', $this->sp_id);
        }

        return $query;
    }

    private function buildOperatorsQuery(): Builder
    {
        $query = $this->buildOperatorsQueryWithoutDate();

        // Apply date range filter only if myDate3 is not empty
        if (!empty($this->myDate3)) {
            try {
                if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
                    $startDate = $this->myDate3[0];
                    $endDate = $this->myDate3[1];

                    // Use more flexible date filtering - handle NULL active_time
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereRaw("DATE(active_time) >= ?", [$startDate])
                          ->whereRaw("DATE(active_time) <= ?", [$endDate])
                          ->orWhereNull('active_time'); // Include operators with NULL active_time
                    });

                } elseif (is_string($this->myDate3) && !empty($this->myDate3)) {
                    if (str_contains($this->myDate3, ' to ')) {
                        $dates = explode(' to ', $this->myDate3);
                        if (count($dates) === 2) {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                            $query->where(function ($q) use ($startDate, $endDate) {
                                $q->whereRaw("DATE(active_time) >= ?", [$startDate])
                                  ->whereRaw("DATE(active_time) <= ?", [$endDate])
                                  ->orWhereNull('active_time');
                            });
                        }
                    }
                }
            } catch (Exception $e) {
                \Log::error('Date range filter error: ' . $e->getMessage());
                // If date parsing fails, don't apply date filter
            }
        }

        return $query;
    }

    public function operators(): LengthAwarePaginator
    {
        $query = $this->buildOperatorsQuery();
        $query->orderBy(...array_values($this->sortBy));
        return $query->paginate(10);
    }

    public function headers(): array
    {
        return [
            ['key' => 'operator_id', 'label' => 'ID Opérateur'],
            ['key' => 'operator_code', 'label' => 'Code', 'class' => 'hidden lg:table-cell'],
            ['key' => 'user_name', 'label' => 'Nom utilisateur'],
            ['key' => 'sp_id', 'label' => 'SP ID', 'class' => 'hidden xl:table-cell'],
            ['key' => 'default_till_id', 'label' => 'Till ID', 'class' => 'hidden xl:table-cell'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'status'],
            ['key' => 'active_time_formatted', 'label' => 'Date activation', 'sortBy' => 'active_time', 'class' => 'hidden lg:table-cell'],
            ['key' => 'kyc_status', 'label' => 'KYC', 'sortable' => false],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false]
        ];
    }

    public function statusOptions(): array
    {
        return [
            ['id' => '01', 'name' => 'Inactif'],
            ['id' => '02', 'name' => 'En attente'], // Added status 02
            ['id' => '03', 'name' => 'Actif'],
            ['id' => '05', 'name' => 'Suspendu'],
            ['id' => '07', 'name' => 'Bloqué'],
            ['id' => '09', 'name' => 'Fermé']
        ];
    }

    public function with(): array
    {
        $operators = $this->operators();

        // Use queries with correct identity type
        $totalOperators = Operator::where('owned_identity_id', $this->organization->biz_org_id)
            ->where('owned_identity_type', 5000)->count();
        $activeOperators = Operator::where('owned_identity_id', $this->organization->biz_org_id)
            ->where('owned_identity_type', 5000)
            ->where('status', '03')->count();
        $operatorsWithKyc = Operator::where('owned_identity_id', $this->organization->biz_org_id)
            ->where('owned_identity_type', 5000)
            ->whereHas('kyc')->count();

        return [
            'organization' => $this->organization,
            'headers' => $this->headers(),
            'operators' => $operators,
            'statusOptions' => $this->statusOptions(),
            'filterCount' => $this->filterCount(),
            'totalOperators' => $totalOperators,
            'activeOperators' => $activeOperators,
            'operatorsWithKyc' => $operatorsWithKyc,
            'debugInfo' => $this->showDebugInfo ? $this->getDebugInfo() : null,
        ];
    }
}; ?>

@php
    $config1 = ['altFormat' => 'd/m/Y'];
    $config2 = ['mode' => 'range'];
@endphp

<div>
    {{-- HEADER --}}
    <x-header title="Opérateurs - {{ $organization->public_name ?? $organization->biz_org_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="{{ $totalOperators }} opérateurs" class="badge-neutral" />
                <x-badge value="{{ $activeOperators }} actifs" class="badge-success" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Debug"
                icon="o-bug-ant"
                wire:click="toggleDebugInfo"
                class="btn-warning btn-sm"
                responsive />

            <x-button
                label="{{ $showFilters ? 'Masquer filtres' : 'Afficher filtres' }}"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                wire:click="toggleFilters"
                class="bg-base-300"
                responsive />

            <x-button label="Retour" icon="o-arrow-left" link="/organizations/{{ $organization->biz_org_id }}" class="btn-outline" />
            <x-button label="Nouvel opérateur" icon="o-plus" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- DEBUG INFO --}}
    @if($debugInfo)
    <x-card class="mb-6 border-warning">
        <div class="bg-warning/10 p-4 rounded">
            <h3 class="font-bold text-warning mb-4">🐛 Debug Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <strong>Organization ID:</strong> {{ $debugInfo['organization_id'] }}
                </div>
                <div>
                    <strong>Total Operators (All):</strong> {{ $debugInfo['total_operators_all'] }}
                </div>
                <div>
                    <strong>Operators for this Org:</strong> {{ $debugInfo['total_operators_for_org'] }}
                </div>
                <div>
                    <strong>Without Date Filter:</strong> {{ $debugInfo['total_operators_without_date_filter'] }}
                </div>
            </div>

            @if($debugInfo['operators_by_identity_type']->count() > 0)
            <div class="mt-4">
                <strong>Operators by Identity Type:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($debugInfo['operators_by_identity_type'] as $item)
                        <li>Type {{ $item->owned_identity_type }}: {{ $item->count }} operators</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if($debugInfo['sample_operators_with_dates']->count() > 0)
            <div class="mt-4">
                <strong>Sample Operators with Dates:</strong>
                <div class="overflow-x-auto mt-2">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Active Time</th>
                                <th>Identity Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($debugInfo['sample_operators_with_dates'] as $op)
                            <tr>
                                <td>{{ $op->operator_id }}</td>
                                <td>{{ $op->user_name }}</td>
                                <td>{{ $op->status }}</td>
                                <td>{{ $op->active_time ?? 'NULL' }}</td>
                                <td>{{ $op->owned_identity_type }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="mt-4">
                <strong>Active Filters:</strong>
                <pre class="bg-base-200 p-2 rounded mt-2 text-xs">{{ json_encode($debugInfo['current_filters'], JSON_PRETTY_PRINT) }}</pre>
            </div>

            <div class="mt-4">
                <strong>SQL Query (with date filter):</strong>
                <pre class="bg-base-200 p-2 rounded mt-2 text-xs">{{ $debugInfo['sql_query'] }}</pre>
                <strong>Bindings:</strong>
                <pre class="bg-base-200 p-2 rounded mt-2 text-xs">{{ json_encode($debugInfo['sql_bindings']) }}</pre>

                <strong>Query without date filter:</strong>
                <pre class="bg-base-200 p-2 rounded mt-2 text-xs">{{ $debugInfo['query_without_date'] }}</pre>
            </div>

            <div class="mt-4">
                <x-button
                    label="Remove ALL filters (including date)"
                    icon="o-trash"
                    wire:click="removeAllFilters"
                    class="btn-error btn-sm" />
            </div>
        </div>
    </x-card>
    @endif

    {{-- STATISTIQUES RAPIDES --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <x-card>
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-users" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Opérateurs</div>
                <div class="stat-value text-primary">{{ $totalOperators }}</div>
                <div class="stat-desc">Tous statuts</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-check-circle" class="w-8 h-8" />
                </div>
                <div class="stat-title">Opérateurs Actifs</div>
                <div class="stat-value text-success">{{ $activeOperators }}</div>
                <div class="stat-desc">Statut actif</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-shield-check" class="w-8 h-8" />
                </div>
                <div class="stat-title">Avec KYC</div>
                <div class="stat-value text-info">{{ $operatorsWithKyc }}</div>
                <div class="stat-desc">KYC complété</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-chart-bar" class="w-8 h-8" />
                </div>
                <div class="stat-title">Taux KYC</div>
                <div class="stat-value text-warning">{{ $totalOperators > 0 ? round(($operatorsWithKyc / $totalOperators) * 100) : 0 }}%</div>
                <div class="stat-desc">Complétude</div>
            </div>
        </x-card>
    </div>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="ID Opérateur" wire:model.live.debounce="operator_id" icon="o-identification" />

            <x-input label="Code opérateur" wire:model.live.debounce="operator_code" icon="o-hashtag" />

            <x-input label="Nom utilisateur" wire:model.live.debounce="user_name" icon="o-user" />

            <x-input label="SP ID" wire:model.live.debounce="sp_id" icon="o-building-storefront" />

            <x-select
                label="Statut"
                :options="$statusOptions"
                wire:model.live="status"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />
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

                <x-button
                    label="Supprimer TOUS les filtres"
                    icon="o-trash"
                    wire:click="removeAllFilters"
                    class="btn-error btn-sm"
                    spinner />
            </div>
        </div>
    </x-card>
    @endif

    {{-- TABLE --}}
    <x-card>
        @if($operators->count() > 0)
            <x-table :headers="$headers" :rows="$operators" :sort-by="$sortBy" with-pagination>
                @scope('cell_status_badge', $operator)
                @php
                    $statusColors = [
                        '01' => 'badge-neutral',
                        '02' => 'badge-info',
                        '03' => 'badge-success',
                        '05' => 'badge-warning',
                        '07' => 'badge-error',
                        '09' => 'badge-ghost'
                    ];
                    $statusLabels = [
                        '01' => 'Inactif',
                        '02' => 'En attente',
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

                @scope('cell_kyc_status', $operator)
                @if($operator->has_kyc)
                    <x-badge value="Complété" class="badge-success" />
                @else
                    <x-badge value="Manquant" class="badge-warning" />
                @endif
                @endscope

                @scope('cell_actions', $operator)
                <div class="flex gap-2">
                    <x-button
                        icon="o-eye"
                        class="btn-ghost btn-sm"
                        tooltip="Voir détails" />

                    <x-button
                        icon="o-identification"
                        class="btn-ghost btn-sm"
                        tooltip="KYC" />

                    <x-button
                        icon="o-pencil"
                        class="btn-ghost btn-sm"
                        tooltip="Modifier" />

                    @if($operator->status === '03')
                    <x-button
                        icon="o-pause"
                        class="btn-ghost btn-sm text-warning"
                        tooltip="Suspendre" />
                    @endif

                    @if($operator->status !== '03')
                    <x-button
                        icon="o-play"
                        class="btn-ghost btn-sm text-success"
                        tooltip="Activer" />
                    @endif
                </div>
                @endscope
            </x-table>
        @else
            <div class="text-center py-12">
                <x-icon name="o-users" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun opérateur trouvé</h3>
                <p class="text-gray-600 mb-4">
                    @if($filterCount > 0)
                        Aucun opérateur ne correspond aux filtres appliqués.
                        <br><small>Essayez de modifier les filtres ou la période de dates.</small>
                    @else
                        Cette organisation n'a pas encore d'opérateurs.
                    @endif
                </p>
                @if($filterCount > 0)
                    <div class="flex gap-2 justify-center">
                        <x-button
                            label="Réinitialiser les filtres"
                            icon="o-x-mark"
                            wire:click="clearFilters"
                            class="btn-outline" />
                        <x-button
                            label="Supprimer TOUS les filtres"
                            icon="o-trash"
                            wire:click="removeAllFilters"
                            class="btn-error btn-sm" />
                    </div>
                @else
                    <x-button
                        label="Créer un opérateur"
                        icon="o-plus"
                        class="btn-primary" />
                @endif
            </div>
        @endif
    </x-card>
</div>
