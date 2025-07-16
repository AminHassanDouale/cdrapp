<?php

use App\Models\Organization;
use App\Models\OrganizationAccount;
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
    public string $account_no = '';

    #[Url]
    public string $alias = '';

    #[Url]
    public string $account_type_id = '';

    #[Url]
    public string $currency = '';

    #[Url]
    public string $account_status = '';

    #[Url]
    public string $value_type = '';

    #[Url]
    public string $min_balance = '';

    #[Url]
    public string $max_balance = '';

    #[Url]
    public array|string $myDate3 = [];

    #[Url]
    public array $sortBy = ['column' => 'account_no', 'direction' => 'asc'];

    public bool $showFilters = false;
    public bool $showDebugInfo = false;

    public function mount(Organization $organization)
    {
        $this->organization = $organization;

        // Set default date range for account creation
        if (empty($this->myDate3)) {
            $this->myDate3 = [
                \Carbon\Carbon::now()->subYears(5)->format('Y-m-d'),
                \Carbon\Carbon::now()->format('Y-m-d')
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
            'account_no',
            'alias',
            'account_type_id',
            'currency',
            'account_status',
            'value_type',
            'min_balance',
            'max_balance',
            'myDate3'
        ]);

        // Reset to default date range
        $this->myDate3 = [
            \Carbon\Carbon::now()->subYears(5)->format('Y-m-d'),
            \Carbon\Carbon::now()->format('Y-m-d')
        ];

        $this->resetPage();
    }

    public function removeAllFilters()
    {
        $this->reset([
            'account_no',
            'alias',
            'account_type_id',
            'currency',
            'account_status',
            'value_type',
            'min_balance',
            'max_balance',
            'myDate3'
        ]);

        $this->myDate3 = []; // Empty date range = no date filtering
        $this->resetPage();
    }

    public function filterCount(): int
    {
        $activeFilters = collect([
            $this->account_no,
            $this->alias,
            $this->account_type_id,
            $this->currency,
            $this->account_status,
            $this->value_type,
            $this->min_balance,
            $this->max_balance,
        ])->filter(fn($value) => !empty($value))->count();

        // Only count date filter if it's different from default
        $defaultStart = \Carbon\Carbon::now()->subYears(5)->format('Y-m-d');
        $defaultEnd = \Carbon\Carbon::now()->format('Y-m-d');

        if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
            if ($this->myDate3[0] !== $defaultStart || $this->myDate3[1] !== $defaultEnd) {
                $activeFilters++;
            }
        } elseif (!empty($this->myDate3)) {
            $activeFilters++;
        }

        return $activeFilters;
    }

    public function exportData()
    {
        try {
            $accounts = $this->buildAccountsQuery()->get();

            $data = [
                'organization' => [
                    'id' => $this->organization->biz_org_id,
                    'name' => $this->organization->public_name ?? $this->organization->biz_org_name,
                ],
                'accounts' => $accounts->map(function ($account) {
                    return [
                        'account_no' => $account->account_no,
                        'alias' => $account->alias,
                        'account_type_id' => $account->account_type_id,
                        'currency' => $account->currency,
                        'balance' => $account->balance,
                        'account_status' => $account->account_status,
                        'value_type' => $account->value_type,
                    ];
                }),
                'summary' => [
                    'total_accounts' => $accounts->count(),
                    'total_balance' => $accounts->sum('balance'),
                    'active_accounts' => $accounts->where('account_status', '03')->count(),
                ],
                'generated_at' => now()->format('Y-m-d H:i:s')
            ];

            $this->dispatch('download-accounts-export', $data);
        } catch (\Exception $e) {
            \Log::error('Export accounts error: ' . $e->getMessage());
            $this->dispatch('export-error', 'Erreur lors de l\'export des comptes');
        }
    }

    public function refreshData()
    {
        $this->resetPage();
        $this->dispatch('data-refreshed', 'Données des comptes mises à jour');
    }

    public function getDebugInfo(): array
    {
        $baseQuery = OrganizationAccount::where('identity_id', $this->organization->biz_org_id);

        return [
            'organization_id' => $this->organization->biz_org_id,
            'total_accounts_all' => OrganizationAccount::count(),
            'total_accounts_for_org' => $baseQuery->count(),
            'accounts_by_type' => OrganizationAccount::where('identity_id', $this->organization->biz_org_id)
                ->selectRaw('account_type_id, count(*) as count')
                ->groupBy('account_type_id')
                ->get(),
            'accounts_by_currency' => OrganizationAccount::where('identity_id', $this->organization->biz_org_id)
                ->selectRaw('currency, count(*) as count')
                ->groupBy('currency')
                ->get(),
            'accounts_by_status' => OrganizationAccount::where('identity_id', $this->organization->biz_org_id)
                ->selectRaw('account_status, count(*) as count')
                ->groupBy('account_status')
                ->get(),
            'balance_statistics' => OrganizationAccount::where('identity_id', $this->organization->biz_org_id)
                ->selectRaw('
                    COALESCE(SUM(balance), 0) as total_balance,
                    COALESCE(AVG(balance), 0) as avg_balance,
                    COALESCE(MAX(balance), 0) as max_balance,
                    COALESCE(MIN(balance), 0) as min_balance
                ')->first(),
            'current_filters' => [
                'account_no' => $this->account_no,
                'account_status' => $this->account_status,
                'currency' => $this->currency,
                'balance_range' => [$this->min_balance, $this->max_balance],
                'date_range' => $this->myDate3,
            ],
            'sql_query' => $this->buildAccountsQuery()->toSql(),
            'sql_bindings' => $this->buildAccountsQuery()->getBindings(),
        ];
    }

    private function buildAccountsQuery(): Builder
    {
        $query = OrganizationAccount::where('identity_id', $this->organization->biz_org_id);

        // Apply filters
        if (!empty($this->account_no)) {
            $query->where('account_no', 'like', "%$this->account_no%");
        }

        if (!empty($this->alias)) {
            $query->where('alias', 'like', "%$this->alias%");
        }

        if (!empty($this->account_type_id)) {
            $query->where('account_type_id', $this->account_type_id);
        }

        if (!empty($this->currency)) {
            $query->where('currency', $this->currency);
        }

        if (!empty($this->account_status)) {
            $query->where('account_status', $this->account_status);
        }

        if (!empty($this->value_type)) {
            $query->where('value_type', $this->value_type);
        }

        // Balance range filters
        if (!empty($this->min_balance)) {
            $query->where('balance', '>=', (float)$this->min_balance);
        }

        if (!empty($this->max_balance)) {
            $query->where('balance', '<=', (float)$this->max_balance);
        }

        // Date range filter - if your table has create_time or similar field
        if (!empty($this->myDate3)) {
            try {
                if (is_array($this->myDate3) && count($this->myDate3) >= 2) {
                    $startDate = $this->myDate3[0];
                    $endDate = $this->myDate3[1];

                    // Check if create_time column exists and apply filter
                    if (\Schema::hasColumn('lbi_ods.t_o_org_account', 'create_time')) {
                        $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                              ->whereRaw("DATE(create_time) <= ?", [$endDate]);
                    }
                } elseif (is_string($this->myDate3) && !empty($this->myDate3)) {
                    if (str_contains($this->myDate3, ' to ')) {
                        $dates = explode(' to ', $this->myDate3);
                        if (count($dates) === 2) {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                            if (\Schema::hasColumn('lbi_ods.t_o_org_account', 'create_time')) {
                                $query->whereRaw("DATE(create_time) >= ?", [$startDate])
                                      ->whereRaw("DATE(create_time) <= ?", [$endDate]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                \Log::error('Date range filter error: ' . $e->getMessage());
            }
        }

        return $query;
    }

    public function accounts(): LengthAwarePaginator
    {
        $query = $this->buildAccountsQuery();
        $query->orderBy(...array_values($this->sortBy));
        return $query->paginate(15);
    }

    public function headers(): array
    {
        return [
            ['key' => 'account_no', 'label' => 'N° Compte'],
            ['key' => 'alias', 'label' => 'Alias', 'class' => 'hidden lg:table-cell'],
            ['key' => 'account_type_id', 'label' => 'Type', 'class' => 'hidden lg:table-cell'],
            ['key' => 'balance_formatted', 'label' => 'Solde', 'sortBy' => 'balance'],
            ['key' => 'currency', 'label' => 'Devise', 'class' => 'hidden xl:table-cell'],
            ['key' => 'value_type', 'label' => 'Type valeur', 'class' => 'hidden xl:table-cell'],
            ['key' => 'status_badge', 'label' => 'Statut', 'sortBy' => 'account_status'],
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

    public function accountTypeOptions(): array
    {
        return [
            ['id' => 'SAVINGS', 'name' => 'Épargne'],
            ['id' => 'CURRENT', 'name' => 'Courant'],
            ['id' => 'ESCROW', 'name' => 'Séquestre'],
            ['id' => 'COMMISSION', 'name' => 'Commission'],
            ['id' => 'FLOAT', 'name' => 'Float'],
            ['id' => 'OTHER', 'name' => 'Autre']
        ];
    }

    public function currencyOptions(): array
    {
        return [
            ['id' => 'EUR', 'name' => 'Euro (EUR)'],
            ['id' => 'USD', 'name' => 'Dollar US (USD)'],
            ['id' => 'GBP', 'name' => 'Livre Sterling (GBP)'],
            ['id' => 'XOF', 'name' => 'Franc CFA (XOF)'],
            ['id' => 'MAD', 'name' => 'Dirham Marocain (MAD)']
        ];
    }

    public function valueTypeOptions(): array
    {
        return [
            ['id' => 'REAL', 'name' => 'Réel'],
            ['id' => 'VIRTUAL', 'name' => 'Virtuel'],
            ['id' => 'POINTS', 'name' => 'Points'],
            ['id' => 'CREDIT', 'name' => 'Crédit']
        ];
    }

    public function with(): array
    {
        $accounts = $this->accounts();

        // Calculate statistics using your existing Organization model
        $totalAccounts = $this->organization->accounts()->count();
        $activeAccounts = $this->organization->activeAccounts()->count();
        $totalBalance = $this->organization->total_balance;
        $accountsByType = $this->organization->accounts()
            ->selectRaw('account_type_id, count(*) as count')
            ->groupBy('account_type_id')
            ->get();

        return [
            'organization' => $this->organization,
            'headers' => $this->headers(),
            'accounts' => $accounts,
            'statusOptions' => $this->statusOptions(),
            'accountTypeOptions' => $this->accountTypeOptions(),
            'currencyOptions' => $this->currencyOptions(),
            'valueTypeOptions' => $this->valueTypeOptions(),
            'filterCount' => $this->filterCount(),
            'totalAccounts' => $totalAccounts,
            'activeAccounts' => $activeAccounts,
            'totalBalance' => $totalBalance,
            'accountsByType' => $accountsByType,
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
    <x-header title="Comptes - {{ $organization->public_name ?? $organization->biz_org_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="{{ $totalAccounts }} comptes" class="badge-neutral" />
                <x-badge value="{{ $activeAccounts }} actifs" class="badge-success" />
                <x-badge value="{{ number_format($totalBalance, 2) }} €" class="badge-primary" />
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
            <x-button label="Nouveau compte" icon="o-plus" class="btn-primary" responsive />
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
                    <strong>Total Accounts (All):</strong> {{ $debugInfo['total_accounts_all'] }}
                </div>
                <div>
                    <strong>Accounts for this Org:</strong> {{ $debugInfo['total_accounts_for_org'] }}
                </div>
                <div>
                    <strong>Balance Total:</strong> {{ number_format($debugInfo['balance_statistics']->total_balance ?? 0, 2) }}€
                </div>
            </div>

            @if($debugInfo['accounts_by_type']->count() > 0)
            <div class="mt-4">
                <strong>Accounts by Type:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($debugInfo['accounts_by_type'] as $item)
                        <li>{{ $item->account_type_id ?? 'N/A' }}: {{ $item->count }} accounts</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if($debugInfo['accounts_by_currency']->count() > 0)
            <div class="mt-4">
                <strong>Accounts by Currency:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($debugInfo['accounts_by_currency'] as $item)
                        <li>{{ $item->currency ?? 'N/A' }}: {{ $item->count }} accounts</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if($debugInfo['accounts_by_status']->count() > 0)
            <div class="mt-4">
                <strong>Accounts by Status:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($debugInfo['accounts_by_status'] as $item)
                        <li>{{ $item->account_status ?? 'N/A' }}: {{ $item->count }} accounts</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="mt-4">
                <strong>Balance Statistics:</strong>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 text-xs">
                    <div>Total: {{ number_format($debugInfo['balance_statistics']->total_balance ?? 0, 2) }}€</div>
                    <div>Average: {{ number_format($debugInfo['balance_statistics']->avg_balance ?? 0, 2) }}€</div>
                    <div>Max: {{ number_format($debugInfo['balance_statistics']->max_balance ?? 0, 2) }}€</div>
                    <div>Min: {{ number_format($debugInfo['balance_statistics']->min_balance ?? 0, 2) }}€</div>
                </div>
            </div>

            <div class="mt-4">
                <strong>SQL Query:</strong>
                <pre class="bg-base-200 p-2 rounded mt-2 text-xs">{{ $debugInfo['sql_query'] }}</pre>
            </div>

            <div class="mt-4">
                <x-button
                    label="Remove ALL filters"
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
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Comptes</div>
                <div class="stat-value text-primary">{{ $totalAccounts }}</div>
                <div class="stat-desc">Tous types</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-check-circle" class="w-8 h-8" />
                </div>
                <div class="stat-title">Comptes Actifs</div>
                <div class="stat-value text-success">{{ $activeAccounts }}</div>
                <div class="stat-desc">Statut actif</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-currency-euro" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Total</div>
                <div class="stat-value text-info">{{ number_format($totalBalance, 2) }}</div>
                <div class="stat-desc">Tous comptes</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-chart-bar" class="w-8 h-8" />
                </div>
                <div class="stat-title">Types Comptes</div>
                <div class="stat-value text-warning">{{ $accountsByType->count() }}</div>
                <div class="stat-desc">Différents types</div>
            </div>
        </x-card>
    </div>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="N° Compte" wire:model.live.debounce="account_no" icon="o-hashtag" />

            <x-input label="Alias" wire:model.live.debounce="alias" icon="o-tag" />

            <x-select
                label="Type de compte"
                :options="$accountTypeOptions"
                wire:model.live="account_type_id"
                icon="o-square-3-stack-3d"
                placeholder="Tous les types"
                placeholder-value="" />

            <x-select
                label="Devise"
                :options="$currencyOptions"
                wire:model.live="currency"
                icon="o-currency-euro"
                placeholder="Toutes devises"
                placeholder-value="" />

            <x-select
                label="Statut"
                :options="$statusOptions"
                wire:model.live="account_status"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />

            <x-select
                label="Type de valeur"
                :options="$valueTypeOptions"
                wire:model.live="value_type"
                icon="o-currency-dollar"
                placeholder="Tous types valeur"
                placeholder-value="" />

            <x-input label="Solde minimum" wire:model.live.debounce="min_balance" type="number" step="0.01" icon="o-arrow-up" />

            <x-input label="Solde maximum" wire:model.live.debounce="max_balance" type="number" step="0.01" icon="o-arrow-down" />
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
        @if($accounts->count() > 0)
            <x-table :headers="$headers" :rows="$accounts" :sort-by="$sortBy" with-pagination>
                @scope('cell_balance_formatted', $account)
                <span class="font-mono {{ $account->balance < 0 ? 'text-error' : 'text-success' }}">
                    {{ number_format($account->balance ?? 0, 2) }}
                </span>
                @endscope

                @scope('cell_status_badge', $account)
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
                <x-badge :value="$statusLabels[$account->account_status] ?? $account->account_status"
                         :class="$statusColors[$account->account_status] ?? 'badge-neutral'" />
                @endscope

                @scope('cell_actions', $account)
                <div class="flex gap-2">
                    <x-button
                        icon="o-eye"
                        link="/organizations/{{ $organization->biz_org_id }}/accounts/{{ $account->account_no }}"
                        class="btn-ghost btn-sm"
                        tooltip="Voir détails" />

                    <x-button
                        icon="o-arrow-right-left"
                        link="/organizations/{{ $organization->biz_org_id }}/accounts/{{ $account->account_no }}/transactions"
                        class="btn-ghost btn-sm"
                        tooltip="Transactions" />

                    <x-button
                        icon="o-pencil"
                        link="/organizations/{{ $organization->biz_org_id }}/accounts/{{ $account->account_no }}/edit"
                        class="btn-ghost btn-sm"
                        tooltip="Modifier" />

                    @if($account->account_status === '03')
                    <x-button
                        icon="o-pause"
                        class="btn-ghost btn-sm text-warning"
                        tooltip="Suspendre" />
                    @endif

                    @if($account->account_status !== '03')
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
                <x-icon name="o-banknotes" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun compte trouvé</h3>
                <p class="text-gray-600 mb-4">
                    @if($filterCount > 0)
                        Aucun compte ne correspond aux filtres appliqués.
                        <br><small>Essayez de modifier les filtres ou la période de dates.</small>
                    @else
                        Cette organisation n'a pas encore de comptes.
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
                        label="Créer un compte"
                        icon="o-plus"
                        class="btn-primary" />
                @endif
            </div>
        @endif
    </x-card>
</div>

{{-- JavaScript for Export and Refresh functionality --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Livewire events
    document.addEventListener('livewire:init', () => {
        Livewire.on('data-refreshed', (message) => {
            // Show success message
            showNotification('success', message);
        });

        Livewire.on('export-error', (message) => {
            // Show error message
            showNotification('error', message);
        });

        Livewire.on('download-accounts-export', (data) => {
            // Create and download JSON file
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'organization-accounts-' + data.organization.id + '-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            showNotification('success', 'Export des comptes terminé');
        });
    });

    // Notification helper function
    function showNotification(type, message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'error' : 'success'} fixed top-4 right-4 z-50 max-w-sm shadow-lg`;
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${type === 'error'
                        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
                    }
                </svg>
                <span>${message}</span>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
});
</script>


