<?php

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

    public Operator $operator;

    #[Url]
    public string $activity_type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'create_time', 'direction' => 'desc'];

    public bool $showFilters = false;

    public function mount(Operator $operator)
    {
        $this->operator = $operator;

        // Set default date range for recent activity
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subMonth()->format('Y-m-d'),
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
            'activity_type',
            'status',
            'myDate3'
        ]);

        $this->myDate3 = [
            \Carbon\Carbon::now()->subMonth()->format('Y-m-d'),
            \Carbon\Carbon::now()->format('Y-m-d')
        ];

        $this->resetPage();
    }

    public function filterCount(): int
    {
        return collect([
            $this->activity_type,
            $this->status,
            is_array($this->myDate3) ? !empty($this->myDate3) : !empty($this->myDate3)
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function activities(): LengthAwarePaginator
    {
        // Note: This is a placeholder since we don't have an actual activity table
        // You would typically query from a transaction or activity log table
        // For now, we'll create mock data based on the operator's information

        $mockActivities = collect([
            [
                'id' => 1,
                'activity_type' => 'LOGIN',
                'description' => 'Connexion à la plateforme',
                'status' => 'SUCCESS',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0...',
                'created_at' => now()->subHours(2),
            ],
            [
                'id' => 2,
                'activity_type' => 'TRANSACTION',
                'description' => 'Transaction de paiement',
                'status' => 'SUCCESS',
                'amount' => 150.00,
                'currency' => 'EUR',
                'created_at' => now()->subHours(5),
            ],
            [
                'id' => 3,
                'activity_type' => 'UPDATE',
                'description' => 'Mise à jour du profil',
                'status' => 'SUCCESS',
                'created_at' => now()->subDay(),
            ],
            [
                'id' => 4,
                'activity_type' => 'LOGIN',
                'description' => 'Tentative de connexion',
                'status' => 'FAILED',
                'ip_address' => '192.168.1.101',
                'created_at' => now()->subDays(2),
            ]
        ]);

        // Apply filters
        if (!empty($this->activity_type)) {
            $mockActivities = $mockActivities->where('activity_type', $this->activity_type);
        }

        if (!empty($this->status)) {
            $mockActivities = $mockActivities->where('status', $this->status);
        }

        // Simulate pagination
        $perPage = 10;
        $currentPage = request()->get('page', 1);
        $total = $mockActivities->count();
        $items = $mockActivities->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    public function headers(): array
    {
        return [
            ['key' => 'activity_type', 'label' => 'Type', 'sortBy' => 'activity_type'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'status'],
            ['key' => 'details', 'label' => 'Détails', 'class' => 'hidden lg:table-cell'],
            ['key' => 'created_at_formatted', 'label' => 'Date/Heure', 'sortBy' => 'created_at', 'class' => 'hidden md:table-cell'],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false]
        ];
    }

    public function activityTypeOptions(): array
    {
        return [
            ['id' => 'LOGIN', 'name' => 'Connexion'],
            ['id' => 'LOGOUT', 'name' => 'Déconnexion'],
            ['id' => 'TRANSACTION', 'name' => 'Transaction'],
            ['id' => 'UPDATE', 'name' => 'Mise à jour'],
            ['id' => 'DELETE', 'name' => 'Suppression'],
            ['id' => 'CREATE', 'name' => 'Création']
        ];
    }

    public function statusOptions(): array
    {
        return [
            ['id' => 'SUCCESS', 'name' => 'Succès'],
            ['id' => 'FAILED', 'name' => 'Échec'],
            ['id' => 'PENDING', 'name' => 'En attente'],
            ['id' => 'CANCELLED', 'name' => 'Annulé']
        ];
    }

    public function with(): array
    {
        $activities = $this->activities();

        // Calculate some stats
        $totalActivities = $activities->total();
        $successfulActivities = collect($activities->items())->where('status', 'SUCCESS')->count();
        $failedActivities = collect($activities->items())->where('status', 'FAILED')->count();

        return [
            'operator' => $this->operator,
            'headers' => $this->headers(),
            'activities' => $activities,
            'activityTypeOptions' => $this->activityTypeOptions(),
            'statusOptions' => $this->statusOptions(),
            'filterCount' => $this->filterCount(),
            'totalActivities' => $totalActivities,
            'successfulActivities' => $successfulActivities,
            'failedActivities' => $failedActivities,
            'successRate' => $totalActivities > 0 ? round(($successfulActivities / $totalActivities) * 100) : 0
        ];
    }
}; ?>

@php
    $config1 = ['altFormat' => 'd/m/Y'];
    $config2 = ['mode' => 'range'];
@endphp

<div>
    {{-- HEADER --}}
    <x-header title="Activité - {{ $operator->public_name ?? $operator->user_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="{{ $totalActivities }} activités" class="badge-neutral" />
                <x-badge value="{{ $successRate }}% succès" class="badge-success" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="{{ $showFilters ? 'Masquer filtres' : 'Afficher filtres' }}"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                wire:click="toggleFilters"
                class="bg-base-300"
                responsive />

            <x-button label="Retour" icon="o-arrow-left" link="/operators/{{ $operator->operator_id }}" class="btn-outline" />
            <x-button label="KYC" icon="o-identification" link="/operators/{{ $operator->operator_id }}/kyc" class="btn-outline" />
            <x-button label="Exporter" icon="o-arrow-down-tray" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- STATISTIQUES RAPIDES --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <x-card>
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-eye" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Activités</div>
                <div class="stat-value text-primary">{{ $totalActivities }}</div>
                <div class="stat-desc">Période sélectionnée</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-check-circle" class="w-8 h-8" />
                </div>
                <div class="stat-title">Succès</div>
                <div class="stat-value text-success">{{ $successfulActivities }}</div>
                <div class="stat-desc">Opérations réussies</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-error">
                    <x-icon name="o-x-circle" class="w-8 h-8" />
                </div>
                <div class="stat-title">Échecs</div>
                <div class="stat-value text-error">{{ $failedActivities }}</div>
                <div class="stat-desc">Opérations échouées</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-chart-bar" class="w-8 h-8" />
                </div>
                <div class="stat-title">Taux de Succès</div>
                <div class="stat-value text-info">{{ $successRate }}%</div>
                <div class="stat-desc">Fiabilité</div>
            </div>
        </x-card>
    </div>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3">
            <x-select
                label="Type d'activité"
                :options="$activityTypeOptions"
                wire:model.live="activity_type"
                icon="o-tag"
                placeholder="Tous les types"
                placeholder-value="" />

            <x-select
                label="Statut"
                :options="$statusOptions"
                wire:model.live="status"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />

            <x-datepicker
                label="Période"
                wire:model="myDate3"
                icon="o-calendar"
                :config="$config2"
                inline />
        </div>

        <div class="flex items-end gap-2">
            <x-button
                label="Réinitialiser filtres"
                icon="o-x-mark"
                wire:click="clearFilters"
                class="btn-outline"
                spinner />
        </div>
    </x-card>
    @endif

    {{-- TABLE --}}
    <x-card>
        <x-table :headers="$headers" :rows="$activities" :sort-by="$sortBy" with-pagination>
            @scope('cell_activity_type', $activity)
            @php
                $typeColors = [
                    'LOGIN' => 'badge-info',
                    'LOGOUT' => 'badge-neutral',
                    'TRANSACTION' => 'badge-primary',
                    'UPDATE' => 'badge-warning',
                    'DELETE' => 'badge-error',
                    'CREATE' => 'badge-success'
                ];
                $typeLabels = [
                    'LOGIN' => 'Connexion',
                    'LOGOUT' => 'Déconnexion',
                    'TRANSACTION' => 'Transaction',
                    'UPDATE' => 'Mise à jour',
                    'DELETE' => 'Suppression',
                    'CREATE' => 'Création'
                ];
            @endphp
            <x-badge :value="$typeLabels[$activity['activity_type']] ?? $activity['activity_type']"
                     :class="$typeColors[$activity['activity_type']] ?? 'badge-neutral'" />
            @endscope

            @scope('cell_status_badge', $activity)
            @php
                $statusColors = [
                    'SUCCESS' => 'badge-success',
                    'FAILED' => 'badge-error',
                    'PENDING' => 'badge-warning',
                    'CANCELLED' => 'badge-neutral'
                ];
                $statusLabels = [
                    'SUCCESS' => 'Succès',
                    'FAILED' => 'Échec',
                    'PENDING' => 'En attente',
                    'CANCELLED' => 'Annulé'
                ];
            @endphp
            <x-badge :value="$statusLabels[$activity['status']] ?? $activity['status']"
                     :class="$statusColors[$activity['status']] ?? 'badge-neutral'" />
            @endscope

            @scope('cell_details', $activity)
            <div class="text-sm">
                @if(isset($activity['ip_address']))
                    <div>IP: {{ $activity['ip_address'] }}</div>
                @endif
                @if(isset($activity['amount']))
                    <div>Montant: {{ number_format($activity['amount'], 2) }} {{ $activity['currency'] ?? 'EUR' }}</div>
                @endif
                @if(isset($activity['user_agent']))
                    <div class="max-w-xs truncate" title="{{ $activity['user_agent'] }}">
                        {{ Str::limit($activity['user_agent'], 30) }}
                    </div>
                @endif
            </div>
            @endscope

            @scope('cell_created_at_formatted', $activity)
            {{ $activity['created_at']->format('d/m/Y H:i:s') }}
            @endscope

            @scope('cell_actions', $activity)
            <div class="flex gap-2">
                <x-button
                    icon="o-eye"
                    class="btn-ghost btn-sm"
                    tooltip="Voir détails" />

                <x-button
                    icon="o-arrow-down-tray"
                    class="btn-ghost btn-sm"
                    tooltip="Exporter" />
            </div>
            @endscope
        </x-table>
    </x-card>
</div>
