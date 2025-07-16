<?php

use App\Models\Organization;
use App\Models\Operator;
use App\Models\OrganizationAccount;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;

new class extends Component {

    #[Url]
    public string $period = '30';

    #[Url]
    public string $organization_type = '';

    #[Url]
    public string $status_filter = '';

    public array $chartData = [];
    public array $operatorStats = [];
    public array $balanceStats = [];
    public array $kycStats = [];
    public array $topOrganizations = [];
    public array $recentActivity = [];
    public array $summary = [];

    public function mount()
    {
        $this->loadAnalyticsData();
    }

    public function updatedPeriod()
    {
        $this->loadAnalyticsData();
    }

    public function updatedOrganizationType()
    {
        $this->loadAnalyticsData();
    }

    public function updatedStatusFilter()
    {
        $this->loadAnalyticsData();
    }

    public function loadAnalyticsData()
    {
        try {
            $this->summary = $this->getSummary();
            $this->chartData = $this->getChartData();
            $this->operatorStats = $this->getOperatorStats();
            $this->balanceStats = $this->getBalanceStats();
            $this->kycStats = $this->getKycStats();
            $this->topOrganizations = $this->getTopOrganizations();
            $this->recentActivity = $this->getRecentActivity();
        } catch (\Exception $e) {
            \Log::error('Analytics data loading error: ' . $e->getMessage());

            // Set safe defaults
            $this->summary = ['total_organizations' => 0, 'total_operators' => 0, 'total_balance' => 0, 'kyc_rate' => 0];
            $this->chartData = ['organizationGrowth' => [], 'operatorGrowth' => [], 'statusDistribution' => [], 'dates' => []];
            $this->operatorStats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0, 'average_per_org' => 0, 'activity_rate' => 0];
            $this->balanceStats = ['total' => 0, 'average' => 0, 'max' => 0, 'min' => 0, 'accounts_with_balance' => 0, 'total_accounts' => 0, 'balance_distribution_rate' => 0];
            $this->kycStats = ['organizations_total' => 0, 'organizations_with_kyc' => 0, 'organizations_kyc_rate' => 0, 'operators_total' => 0, 'operators_with_kyc' => 0, 'operators_kyc_rate' => 0];
            $this->topOrganizations = [];
            $this->recentActivity = [];
        }
    }

    private function getSummary(): array
    {
        $totalOrganizations = Organization::count();
        $totalOperators = Operator::where('owned_identity_type', 5000)->count();
        $totalBalance = OrganizationAccount::sum('balance') ?? 0;
        $organizationsWithKyc = Organization::whereHas('kyc')->count();
        $kycRate = $totalOrganizations > 0 ? round(($organizationsWithKyc / $totalOrganizations) * 100, 1) : 0;

        return [
            'total_organizations' => $totalOrganizations,
            'total_operators' => $totalOperators,
            'total_balance' => $totalBalance,
            'kyc_rate' => $kycRate
        ];
    }

    private function getChartData(): array
    {
        $days = (int) $this->period;
        $startDate = now()->subDays($days);

        try {
            $organizationGrowth = Organization::selectRaw("DATE(COALESCE(create_time, '1970-01-01')) as date, COUNT(*) as count")
                ->whereRaw("create_time IS NOT NULL AND DATE(create_time) >= ?", [$startDate->format('Y-m-d')])
                ->when($this->organization_type, function ($query) {
                    $query->where('organization_type', $this->organization_type);
                })
                ->when($this->status_filter, function ($query) {
                    $query->where('status', $this->status_filter);
                })
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();

            $operatorGrowth = Operator::selectRaw("DATE(COALESCE(create_time, '1970-01-01')) as date, COUNT(*) as count")
                ->whereRaw("create_time IS NOT NULL AND DATE(create_time) >= ?", [$startDate->format('Y-m-d')])
                ->where('owned_identity_type', 5000)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();

            $statusDistribution = Organization::selectRaw('status, COUNT(*) as count')
                ->when($this->organization_type, function ($query) {
                    $query->where('organization_type', $this->organization_type);
                })
                ->whereNotNull('status')
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $this->getStatusLabel($item->status),
                        'count' => $item->count,
                        'color' => $this->getStatusColor($item->status)
                    ];
                })
                ->toArray();

            return [
                'organizationGrowth' => $organizationGrowth,
                'operatorGrowth' => $operatorGrowth,
                'statusDistribution' => $statusDistribution,
                'dates' => $this->generateDateRange($startDate, now())
            ];

        } catch (\Exception $e) {
            \Log::error('Chart data error: ' . $e->getMessage());
            return [
                'organizationGrowth' => [],
                'operatorGrowth' => [],
                'statusDistribution' => [],
                'dates' => []
            ];
        }
    }

    private function getOperatorStats(): array
    {
        try {
            $totalOperators = Operator::where('owned_identity_type', 5000)->count();
            $activeOperators = Operator::where('owned_identity_type', 5000)->where('status', '03')->count();
            $inactiveOperators = Operator::where('owned_identity_type', 5000)->where('status', '01')->count();
            $suspendedOperators = Operator::where('owned_identity_type', 5000)->where('status', '05')->count();

            $totalOrganizations = Organization::count();
            $averageOperatorsPerOrg = $totalOrganizations > 0 ? round($totalOperators / $totalOrganizations, 2) : 0;

            return [
                'total' => $totalOperators,
                'active' => $activeOperators,
                'inactive' => $inactiveOperators,
                'suspended' => $suspendedOperators,
                'average_per_org' => $averageOperatorsPerOrg,
                'activity_rate' => $totalOperators > 0 ? round(($activeOperators / $totalOperators) * 100, 1) : 0
            ];
        } catch (\Exception $e) {
            \Log::error('Operator stats error: ' . $e->getMessage());
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'suspended' => 0,
                'average_per_org' => 0,
                'activity_rate' => 0
            ];
        }
    }

    private function getBalanceStats(): array
    {
        try {
            $balances = OrganizationAccount::selectRaw('
                COALESCE(SUM(COALESCE(balance, 0)), 0) as total_balance,
                COALESCE(AVG(COALESCE(balance, 0)), 0) as avg_balance,
                COALESCE(MAX(COALESCE(balance, 0)), 0) as max_balance,
                COALESCE(MIN(COALESCE(balance, 0)), 0) as min_balance
            ')->first();

            $accountsWithBalance = OrganizationAccount::where('balance', '>', 0)->count();
            $totalAccounts = OrganizationAccount::count();

            return [
                'total' => $balances->total_balance ?? 0,
                'average' => $balances->avg_balance ?? 0,
                'max' => $balances->max_balance ?? 0,
                'min' => $balances->min_balance ?? 0,
                'accounts_with_balance' => $accountsWithBalance,
                'total_accounts' => $totalAccounts,
                'balance_distribution_rate' => $totalAccounts > 0 ?
                    round(($accountsWithBalance / $totalAccounts) * 100, 1) : 0
            ];
        } catch (\Exception $e) {
            \Log::error('Balance stats error: ' . $e->getMessage());
            return [
                'total' => 0,
                'average' => 0,
                'max' => 0,
                'min' => 0,
                'accounts_with_balance' => 0,
                'total_accounts' => 0,
                'balance_distribution_rate' => 0
            ];
        }
    }

    private function getKycStats(): array
    {
        try {
            $totalOrganizations = Organization::count();
            $organizationsWithKyc = Organization::whereHas('kyc')->count();
            $totalOperators = Operator::where('owned_identity_type', 5000)->count();
            $operatorsWithKyc = Operator::where('owned_identity_type', 5000)->whereHas('kyc')->count();

            return [
                'organizations_total' => $totalOrganizations,
                'organizations_with_kyc' => $organizationsWithKyc,
                'organizations_kyc_rate' => $totalOrganizations > 0 ?
                    round(($organizationsWithKyc / $totalOrganizations) * 100, 1) : 0,
                'operators_total' => $totalOperators,
                'operators_with_kyc' => $operatorsWithKyc,
                'operators_kyc_rate' => $totalOperators > 0 ?
                    round(($operatorsWithKyc / $totalOperators) * 100, 1) : 0
            ];
        } catch (\Exception $e) {
            \Log::error('KYC stats error: ' . $e->getMessage());
            return [
                'organizations_total' => 0,
                'organizations_with_kyc' => 0,
                'organizations_kyc_rate' => 0,
                'operators_total' => 0,
                'operators_with_kyc' => 0,
                'operators_kyc_rate' => 0
            ];
        }
    }

    private function getTopOrganizations(): array
    {
        try {
            return Organization::with(['accounts'])
                ->when($this->organization_type, function ($query) {
                    $query->where('organization_type', $this->organization_type);
                })
                ->when($this->status_filter, function ($query) {
                    $query->where('status', $this->status_filter);
                })
                ->limit(50)
                ->get()
                ->map(function ($org) {
                    $operatorsCount = Operator::where('owned_identity_id', $org->biz_org_id)
                        ->where('owned_identity_type', 5000)
                        ->count();
                    $balance = $org->accounts->sum('balance') ?? 0;
                    $kycStatus = $org->has_kyc ?? false;

                    return [
                        'id' => $org->biz_org_id,
                        'name' => $org->public_name ?? $org->biz_org_name ?? 'N/A',
                        'type' => $org->organization_type,
                        'status' => $org->status,
                        'operators_count' => $operatorsCount,
                        'balance' => $balance,
                        'kyc_complete' => $kycStatus,
                        'created_at' => $org->create_time_formatted ?? '-'
                    ];
                })
                ->sortByDesc('balance')
                ->take(10)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            \Log::error('Top organizations error: ' . $e->getMessage());
            return [];
        }
    }

    private function getRecentActivity(): array
    {
        try {
            $recentOrgs = Organization::whereNotNull('create_time')
                ->orderBy('create_time', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($org) {
                    return [
                        'type' => 'organization_created',
                        'title' => 'Nouvelle organisation créée',
                        'description' => $org->public_name ?? $org->biz_org_name ?? 'N/A',
                        'time' => $org->create_time_formatted ?? '-',
                        'status' => $org->status
                    ];
                });

            $recentOperators = Operator::where('owned_identity_type', 5000)
                ->whereNotNull('create_time')
                ->orderBy('create_time', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($op) {
                    return [
                        'type' => 'operator_created',
                        'title' => 'Nouvel opérateur créé',
                        'description' => $op->user_name ?? 'N/A',
                        'time' => $op->create_time_formatted ?? '-',
                        'status' => $op->status
                    ];
                });

            return $recentOrgs->concat($recentOperators)
                ->sortByDesc('time')
                ->take(10)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            \Log::error('Recent activity error: ' . $e->getMessage());
            return [];
        }
    }

    private function generateDateRange($startDate, $endDate): array
    {
        $dates = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    private function getStatusLabel($status): string
    {
        $labels = [
            '01' => 'Inactif',
            '03' => 'Actif',
            '05' => 'Suspendu',
            '07' => 'Bloqué',
            '09' => 'Fermé'
        ];

        return $labels[$status] ?? $status;
    }

    private function getStatusColor($status): string
    {
        $colors = [
            '01' => '#6B7280',
            '03' => '#10B981',
            '05' => '#F59E0B',
            '07' => '#EF4444',
            '09' => '#374151'
        ];

        return $colors[$status] ?? '#6B7280';
    }

    public function periodOptions(): array
    {
        return [
            ['id' => '7', 'name' => '7 derniers jours'],
            ['id' => '30', 'name' => '30 derniers jours'],
            ['id' => '90', 'name' => '90 derniers jours'],
            ['id' => '365', 'name' => '1 an']
        ];
    }

    public function organizationTypeOptions(): array
    {
        try {
            $types = Organization::select('organization_type')
                ->distinct()
                ->whereNotNull('organization_type')
                ->where('organization_type', '!=', '')
                ->orderBy('organization_type')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->organization_type,
                        'name' => 'Type ' . $item->organization_type
                    ];
                })
                ->toArray();

            return $types;
        } catch (\Exception $e) {
            \Log::error('Organization type options error: ' . $e->getMessage());
            return [];
        }
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

    public function exportData()
    {
        try {
            $data = [
                'summary' => $this->summary,
                'operator_stats' => $this->operatorStats,
                'balance_stats' => $this->balanceStats,
                'kyc_stats' => $this->kycStats,
                'top_organizations' => $this->topOrganizations,
                'generated_at' => now()->format('Y-m-d H:i:s')
            ];

            $this->dispatch('download-analytics-export', $data);
        } catch (\Exception $e) {
            \Log::error('Export data error: ' . $e->getMessage());
            $this->dispatch('export-error', 'Erreur lors de l\'export des données');
        }
    }

    public function refreshData()
    {
        $this->loadAnalyticsData();
        $this->dispatch('data-refreshed', 'Données mises à jour');
    }

    public function with(): array
    {
        return [
            'summary' => $this->summary,
            'chartData' => $this->chartData,
            'operatorStats' => $this->operatorStats,
            'balanceStats' => $this->balanceStats,
            'kycStats' => $this->kycStats,
            'topOrganizations' => $this->topOrganizations,
            'recentActivity' => $this->recentActivity,
            'periodOptions' => $this->periodOptions(),
            'organizationTypeOptions' => $this->organizationTypeOptions(),
            'statusOptions' => $this->statusOptions()
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Analytics des Organisations" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="Données en temps réel" class="badge-success" />
                <x-badge value="Dernière mise à jour: {{ now()->format('H:i') }}" class="badge-neutral" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Actualiser"
                icon="o-arrow-path"
                wire:click="refreshData"
                class="btn-ghost"
                spinner="refreshData" />

            <x-button
                label="Exporter"
                icon="o-arrow-down-tray"
                wire:click="exportData"
                class="btn-outline"
                spinner="exportData" />

            <x-button
                label="Organisations"
                icon="o-building-office"
                link="/organizations"
                class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-select
                label="Période"
                :options="$periodOptions"
                wire:model.live="period"
                icon="o-calendar" />

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
                wire:model.live="status_filter"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />
        </div>
    </x-card>

    {{-- KEY METRICS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Organizations --}}
        <x-card>
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-building-office" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Organisations</div>
                <div class="stat-value text-primary">{{ number_format($summary['total_organizations']) }}</div>
                <div class="stat-desc">Système complet</div>
            </div>
        </x-card>

        {{-- Total Operators --}}
        <x-card>
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-users" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Opérateurs</div>
                <div class="stat-value text-success">{{ number_format($operatorStats['total']) }}</div>
                <div class="stat-desc">{{ $operatorStats['activity_rate'] }}% actifs</div>
            </div>
        </x-card>

        {{-- Total Balance --}}
        <x-card>
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Total</div>
                <div class="stat-value text-warning font-mono">{{ number_format($balanceStats['total'], 2, ',', ' ') }}€</div>
                <div class="stat-desc">Moyenne: {{ number_format($balanceStats['average'], 2, ',', ' ') }}€</div>
            </div>
        </x-card>

        {{-- KYC Completion --}}
        <x-card>
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-shield-check" class="w-8 h-8" />
                </div>
                <div class="stat-title">Taux KYC</div>
                <div class="stat-value text-info">{{ $kycStats['organizations_kyc_rate'] }}%</div>
                <div class="stat-desc">{{ $kycStats['organizations_with_kyc'] }}/{{ $kycStats['organizations_total'] }} complétés</div>
            </div>
        </x-card>
    </div>

    {{-- CHARTS ROW --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Organization Growth Chart --}}
        <x-card title="Croissance des Organisations">
            <div class="h-80 relative">
                <canvas id="organizationGrowthChart"></canvas>
            </div>
        </x-card>

        {{-- Status Distribution --}}
        <x-card title="Répartition par Statut">
            <div class="h-80 relative">
                <canvas id="statusDistributionChart"></canvas>
            </div>
        </x-card>
    </div>

    {{-- DETAILED STATS --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Operator Statistics --}}
        <x-card title="Statistiques Opérateurs">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm">Opérateurs Actifs</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-success">{{ number_format($operatorStats['active']) }}</span>
                        <div class="badge badge-success badge-sm">{{ $operatorStats['activity_rate'] }}%</div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Opérateurs Inactifs</span>
                    <span class="font-semibold text-neutral">{{ number_format($operatorStats['inactive']) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Opérateurs Suspendus</span>
                    <span class="font-semibold text-warning">{{ number_format($operatorStats['suspended']) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Moyenne par Org</span>
                    <span class="font-semibold text-info">{{ $operatorStats['average_per_org'] }}</span>
                </div>
            </div>
        </x-card>

        {{-- Balance Statistics --}}
        <x-card title="Statistiques Soldes">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm">Solde Maximum</span>
                    <span class="font-semibold text-success font-mono">{{ number_format($balanceStats['max'], 2, ',', ' ') }}€</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Solde Minimum</span>
                    <span class="font-semibold text-error font-mono">{{ number_format($balanceStats['min'], 2, ',', ' ') }}€</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Comptes avec Solde</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ number_format($balanceStats['accounts_with_balance']) }}</span>
                        <div class="badge badge-info badge-sm">{{ $balanceStats['balance_distribution_rate'] }}%</div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Total Comptes</span>
                    <span class="font-semibold">{{ number_format($balanceStats['total_accounts']) }}</span>
                </div>
            </div>
        </x-card>

        {{-- KYC Statistics --}}
        <x-card title="Statistiques KYC">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm">Org avec KYC</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-success">{{ number_format($kycStats['organizations_with_kyc']) }}</span>
                        <div class="badge badge-success badge-sm">{{ $kycStats['organizations_kyc_rate'] }}%</div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Op avec KYC</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-info">{{ number_format($kycStats['operators_with_kyc']) }}</span>
                        <div class="badge badge-info badge-sm">{{ $kycStats['operators_kyc_rate'] }}%</div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Total Org</span>
                    <span class="font-semibold">{{ number_format($kycStats['organizations_total']) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Total Op</span>
                    <span class="font-semibold">{{ number_format($kycStats['operators_total']) }}</span>
                </div>
            </div>
        </x-card>
    </div>

    {{-- BOTTOM SECTION --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Organizations --}}
        <x-card title="Top 10 Organisations par Solde">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>Organisation</th>
                            <th>Opérateurs</th>
                            <th>Solde</th>
                            <th>KYC</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topOrganizations as $org)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral-focus text-neutral-content rounded-full w-8">
                                            <span class="text-xs">{{ substr($org['name'], 0, 2) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm">{{ $org['name'] }}</div>
                                        <div class="text-xs opacity-50">{{ $org['id'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <x-badge value="{{ $org['operators_count'] }}" class="badge-neutral badge-sm" />
                            </td>
                            <td>
                                <span class="font-mono text-sm">{{ number_format($org['balance'], 2, ',', ' ') }}€</span>
                            </td>
                            <td>
                                @if($org['kyc_complete'])
                                    <x-badge value="Complété" class="badge-success badge-sm" />
                                @else
                                    <x-badge value="Manquant" class="badge-warning badge-sm" />
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusLabels = [
                                        '01' => 'Inactif',
                                        '03' => 'Actif',
                                        '05' => 'Suspendu',
                                        '07' => 'Bloqué',
                                        '09' => 'Fermé'
                                    ];
                                @endphp
                                <x-badge
                                    value="{{ $statusLabels[$org['status']] ?? $org['status'] }}"
                                    class="badge-{{ $org['status'] === '03' ? 'success' : 'neutral' }} badge-sm" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <div class="text-gray-500">
                                    <x-icon name="o-building-office" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                    <p>Aucune organisation trouvée</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Recent Activity --}}
        <x-card title="Activité Récente">
            <div class="space-y-4 max-h-96 overflow-y-auto">
                @forelse($recentActivity as $activity)
                <div class="flex items-start gap-4 p-2 hover:bg-base-200 rounded-lg transition-colors">
                    <div class="avatar">
                        <div class="w-10 h-10 rounded-full bg-base-200 flex items-center justify-center">
                            @if($activity['type'] === 'organization_created')
                                <x-icon name="o-building-office" class="w-5 h-5 text-primary" />
                            @else
                                <x-icon name="o-user" class="w-5 h-5 text-success" />
                            @endif
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-sm">{{ $activity['title'] }}</h4>
                            <span class="text-xs opacity-60">{{ $activity['time'] }}</span>
                        </div>
                        <p class="text-sm opacity-80">{{ $activity['description'] }}</p>
                        @php
                            $statusLabels = [
                                '01' => 'Inactif',
                                '03' => 'Actif',
                                '05' => 'Suspendu',
                                '07' => 'Bloqué',
                                '09' => 'Fermé'
                            ];
                        @endphp
                        <x-badge
                            value="{{ $statusLabels[$activity['status']] ?? $activity['status'] }}"
                            class="badge-{{ $activity['status'] === '03' ? 'success' : 'neutral' }} badge-xs mt-1" />
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <div class="text-gray-500">
                        <x-icon name="o-clock" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>Aucune activité récente</p>
                    </div>
                </div>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- PERFORMANCE METRICS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Organization Performance --}}
        <x-card title="Performance des Organisations">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm">Organisations Actives</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-success">{{ number_format(collect($topOrganizations)->where('status', '03')->count()) }}</span>
                        <div class="badge badge-success badge-sm">
                            {{ count($topOrganizations) > 0 ? round((collect($topOrganizations)->where('status', '03')->count() / count($topOrganizations)) * 100, 1) : 0 }}%
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Organisations avec Opérateurs</span>
                    <span class="font-semibold text-info">{{ number_format(collect($topOrganizations)->where('operators_count', '>', 0)->count()) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Organisations avec KYC</span>
                    <span class="font-semibold text-success">{{ number_format(collect($topOrganizations)->where('kyc_complete', true)->count()) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Solde Moyen Top 10</span>
                    <span class="font-semibold text-warning font-mono">
                        {{ number_format(count($topOrganizations) > 0 ? collect($topOrganizations)->avg('balance') : 0, 2, ',', ' ') }}€
                    </span>
                </div>
            </div>
        </x-card>

        {{-- System Health --}}
        <x-card title="Santé du Système">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm">Taux d'Activation Org</span>
                    <div class="flex items-center gap-2">
                        @php
                            $orgActivationRate = $summary['total_organizations'] > 0 ?
                                round((collect($topOrganizations)->where('status', '03')->count() / $summary['total_organizations']) * 100, 1) : 0;
                        @endphp
                        <span class="font-semibold text-success">{{ $orgActivationRate }}%</span>
                        <div class="badge badge-{{ $orgActivationRate > 70 ? 'success' : 'warning' }} badge-sm">
                            {{ $orgActivationRate > 70 ? 'OK' : 'LOW' }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Taux d'Activation Op</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-success">{{ $operatorStats['activity_rate'] }}%</span>
                        <div class="badge badge-{{ $operatorStats['activity_rate'] > 70 ? 'success' : 'warning' }} badge-sm">
                            {{ $operatorStats['activity_rate'] > 70 ? 'OK' : 'LOW' }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Complétude KYC</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-info">{{ $kycStats['organizations_kyc_rate'] }}%</span>
                        <div class="badge badge-{{ $kycStats['organizations_kyc_rate'] > 60 ? 'success' : 'warning' }} badge-sm">
                            {{ $kycStats['organizations_kyc_rate'] > 60 ? 'OK' : 'LOW' }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm">Distribution Soldes</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-warning">{{ $balanceStats['balance_distribution_rate'] }}%</span>
                        <div class="badge badge-{{ $balanceStats['balance_distribution_rate'] > 50 ? 'success' : 'warning' }} badge-sm">
                            {{ $balanceStats['balance_distribution_rate'] > 50 ? 'OK' : 'LOW' }}
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- QUICK INSIGHTS --}}
    <x-card title="Insights Rapides">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $summary['total_organizations'] }}</div>
                <div class="text-sm text-blue-700">Organisations totales</div>
            </div>

            <div class="text-center p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $operatorStats['active'] }}</div>
                <div class="text-sm text-green-700">Opérateurs actifs</div>
            </div>

            <div class="text-center p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600">{{ number_format($balanceStats['total'], 0, ',', ' ') }}€</div>
                <div class="text-sm text-yellow-700">Solde total</div>
            </div>

            <div class="text-center p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                <div class="text-2xl font-bold text-purple-600">{{ $kycStats['organizations_kyc_rate'] }}%</div>
                <div class="text-sm text-purple-700">KYC complétés</div>
            </div>
        </div>
    </x-card>
</div>

{{-- Chart.js Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper function to safely get chart data
    function getChartData(key, defaultValue = []) {
        const chartData = @json($chartData);
        return chartData && chartData[key] ? chartData[key] : defaultValue;
    }

    // Organization Growth Chart
    const orgGrowthCtx = document.getElementById('organizationGrowthChart');
    if (orgGrowthCtx) {
        const organizationGrowthChart = new Chart(orgGrowthCtx, {
            type: 'line',
            data: {
                labels: getChartData('dates'),
                datasets: [{
                    label: 'Organisations créées',
                    data: Object.values(getChartData('organizationGrowth')),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }, {
                    label: 'Opérateurs créés',
                    data: Object.values(getChartData('operatorGrowth')),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: 'rgb(16, 185, 129)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });
    }

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusDistributionChart');
    if (statusCtx) {
        const statusDistribution = getChartData('statusDistribution');
        const statusDistributionChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusDistribution.map(item => item.status),
                datasets: [{
                    data: statusDistribution.map(item => item.count),
                    backgroundColor: statusDistribution.map(item => item.color),
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%',
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });
    }

    // Refresh charts on data update
    document.addEventListener('livewire:init', () => {
        Livewire.on('data-refreshed', (message) => {
            // Reload the page to refresh charts with new data
            window.location.reload();
        });

        Livewire.on('export-error', (message) => {
            alert('Erreur: ' + message);
        });

        Livewire.on('download-analytics-export', (data) => {
            // Create and download JSON file
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'organizations-analytics-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    });
});
</script>
