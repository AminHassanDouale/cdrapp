<?php

use App\Models\CustomerAccount;
use App\Models\Customer;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    use WithPagination;

    // Search & Filters
    public string $search = '';
    public string $accountNo = '';
    public string $customerId = '';
    public string $customerName = '';
    public string $statusFilter = '';
    public string $currencyFilter = '';
    public string $typeFilter = '';
    public string $balanceFilter = '';
    public array $dateRange = [];

    // Sorting
    public array $sortBy = ['column' => 'open_date', 'direction' => 'desc'];

    // UI State
    public bool $showFilters = true;
    public bool $showAdvancedFilters = false;
    public string $selectedView = 'table'; // table, cards, summary

    // Bulk Operations
    public array $selectedAccounts = [];
    public bool $selectAll = false;

    public function mount()
    {
        // Set default date range to last 30 days
        if (empty($this->dateRange)) {
            $this->dateRange = [
                \Carbon\Carbon::now()->subDays(30)->format('Y-m-d'),
                \Carbon\Carbon::now()->format('Y-m-d')
            ];
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function toggleAdvancedFilters()
    {
        $this->showAdvancedFilters = !$this->showAdvancedFilters;
    }

    public function clearFilters()
    {
        $this->reset([
            'search',
            'accountNo',
            'customerId',
            'customerName',
            'statusFilter',
            'currencyFilter',
            'typeFilter',
            'balanceFilter'
        ]);

        // Reset date range
        $this->dateRange = [
            \Carbon\Carbon::now()->subDays(30)->format('Y-m-d'),
            \Carbon\Carbon::now()->format('Y-m-d')
        ];

        $this->resetPage();
    }

    public function setView(string $view)
    {
        $this->selectedView = $view;
        $this->resetPage();
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedAccounts = $this->accounts()->pluck('account_no')->toArray();
        } else {
            $this->selectedAccounts = [];
        }
    }

    public function filterCount(): int
    {
        return collect([
            $this->search,
            $this->accountNo,
            $this->customerId,
            $this->customerName,
            $this->statusFilter,
            $this->currencyFilter,
            $this->typeFilter,
            $this->balanceFilter,
            !empty($this->dateRange) ? 'date_range' : null
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function accounts()
    {
        $query = CustomerAccount::with('customer');

        // Global search
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('account_no', 'like', "%{$this->search}%")
                  ->orWhere('alias', 'like', "%{$this->search}%")
                  ->orWhere('account_name', 'like', "%{$this->search}%")
                  ->orWhereHas('customer', function($customerQuery) {
                      $customerQuery->where('public_name', 'like', "%{$this->search}%")
                                   ->orWhere('user_name', 'like', "%{$this->search}%")
                                   ->orWhere('customer_id', 'like', "%{$this->search}%");
                  });
            });
        }

        // Specific filters
        if (!empty($this->accountNo)) {
            $query->where('account_no', 'like', "%{$this->accountNo}%");
        }

        if (!empty($this->customerId)) {
            $query->where('identity_id', 'like', "%{$this->customerId}%");
        }

        if (!empty($this->customerName)) {
            $query->whereHas('customer', function($q) {
                $q->where('public_name', 'like', "%{$this->customerName}%")
                  ->orWhere('user_name', 'like', "%{$this->customerName}%");
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('account_status', $this->statusFilter);
        }

        if (!empty($this->currencyFilter)) {
            $query->where('currency', $this->currencyFilter);
        }

        if (!empty($this->typeFilter)) {
            $query->where('account_type_id', $this->typeFilter);
        }

        // Balance filters
        switch ($this->balanceFilter) {
            case 'positive':
                $query->where('balance', '>', 0);
                break;
            case 'negative':
                $query->where('balance', '<', 0);
                break;
            case 'zero':
                $query->where('balance', '=', 0);
                break;
            case 'high':
                $query->where('balance', '>', 100000);
                break;
            case 'very_high':
                $query->where('balance', '>', 1000000);
                break;
        }

        // Date range filter
        if (!empty($this->dateRange) && count($this->dateRange) >= 2) {
            $query->whereRaw("DATE(open_date) >= ?", [$this->dateRange[0]])
                  ->whereRaw("DATE(open_date) <= ?", [$this->dateRange[1]]);
        }

        return $query->orderBy(...array_values($this->sortBy))->paginate(20);
    }

    public function getAccountStatsProperty()
    {
        $baseQuery = CustomerAccount::query();

        // Apply same filters as main query for consistent stats
        if (!empty($this->dateRange) && count($this->dateRange) >= 2) {
            $baseQuery->whereRaw("DATE(open_date) >= ?", [$this->dateRange[0]])
                     ->whereRaw("DATE(open_date) <= ?", [$this->dateRange[1]]);
        }

        return [
            'total' => $baseQuery->count(),
            'active' => $baseQuery->where('account_status', '03')->count(),
            'inactive' => $baseQuery->where('account_status', '!=', '03')->count(),
            'blocked' => $baseQuery->whereIn('account_status', ['04', '06'])->count(),
            'total_balance' => $baseQuery->sum('balance'),
            'total_reserved' => $baseQuery->sum('reserved_balance'),
            'avg_balance' => $baseQuery->avg('balance'),
            'currencies' => CustomerAccount::distinct()->pluck('currency')->filter()->values(),
            'account_types' => CustomerAccount::distinct()->pluck('account_type_id')->filter()->values(),
            'accounts_with_balance' => $baseQuery->where('balance', '>', 0)->count()
        ];
    }

    public function getAccountStatusOptionsProperty()
    {
        return [
            '01' => 'En attente d\'ouverture',
            '02' => 'En cours d\'ouverture',
            '03' => 'Actif',
            '04' => 'Suspendu',
            '05' => 'Fermé',
            '06' => 'Bloqué',
            '07' => 'Dormant'
        ];
    }

    public function exportAccounts()
    {
        // Logic to export filtered accounts
        $this->dispatch('export-accounts', filters: [
            'search' => $this->search,
            'status' => $this->statusFilter,
            'currency' => $this->currencyFilter,
            'date_range' => $this->dateRange
        ]);
    }

    public function bulkUpdateStatus(string $status)
    {
        if (empty($this->selectedAccounts)) {
            $this->dispatch('notify', message: 'Aucun compte sélectionné', type: 'warning');
            return;
        }

        // Logic to bulk update account status
        $this->dispatch('bulk-update-status', accounts: $this->selectedAccounts, status: $status);
        $this->selectedAccounts = [];
        $this->selectAll = false;
    }

    public function with(): array
    {
        return [
            'accounts' => $this->accounts(),
            'accountStats' => $this->account_stats,
            'accountStatusOptions' => $this->account_status_options,
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Gestion des Comptes Clients" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-badge value="{{ number_format($accountStats['total']) }} comptes" class="badge-info" />
                <x-badge value="{{ number_format($accountStats['total_balance'], 0) }}€" class="badge-success" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Exporter"
                      icon="o-arrow-down-tray"
                      wire:click="exportAccounts"
                      class="btn-outline" />
            @can('customer-accounts.create')
                <x-button label="Nouveau Compte"
                          icon="o-plus"
                          link="/customer-accounts/create"
                          class="btn-primary" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- STATISTICS DASHBOARD --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-5">
        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-credit-card" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Comptes</div>
                <div class="stat-value text-primary">{{ number_format($accountStats['total']) }}</div>
                <div class="stat-desc">{{ number_format($accountStats['active']) }} actifs</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Total</div>
                <div class="stat-value text-success">{{ number_format($accountStats['total_balance'] / 1000000, 1) }}M€</div>
                <div class="stat-desc">Moyenne: {{ number_format($accountStats['avg_balance'], 0) }}€</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-pause" class="w-8 h-8" />
                </div>
                <div class="stat-title">Suspendus/Bloqués</div>
                <div class="stat-value text-warning">{{ number_format($accountStats['blocked']) }}</div>
                <div class="stat-desc">{{ number_format(($accountStats['blocked'] / max($accountStats['total'], 1)) * 100, 1) }}% du total</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-currency-euro" class="w-8 h-8" />
                </div>
                <div class="stat-title">Devises</div>
                <div class="stat-value text-info">{{ $accountStats['currencies']->count() }}</div>
                <div class="stat-desc">{{ $accountStats['currencies']->take(3)->join(', ') }}</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-accent">
                    <x-icon name="o-chart-bar" class="w-8 h-8" />
                </div>
                <div class="stat-title">Avec Solde</div>
                <div class="stat-value text-accent">{{ number_format($accountStats['accounts_with_balance']) }}</div>
                <div class="stat-desc">{{ number_format(($accountStats['accounts_with_balance'] / max($accountStats['total'], 1)) * 100, 1) }}%</div>
            </div>
        </div>
    </div>

    {{-- FILTERS SECTION --}}
    <x-card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Recherche et Filtres</h3>
            <div class="flex gap-2">
                <x-button label="{{ $showFilters ? 'Masquer' : 'Afficher' }} filtres"
                          icon="o-funnel"
                          :badge="$filterCount"
                          wire:click="toggleFilters"
                          class="btn-ghost" />
                @if($filterCount > 0)
                    <x-button label="Réinitialiser"
                              icon="o-x-mark"
                              wire:click="clearFilters"
                              class="btn-outline" />
                @endif
            </div>
        </div>

        {{-- MAIN SEARCH --}}
        <div class="mb-4">
            <x-input placeholder="Recherche globale: N° compte, nom client, alias..."
                     wire:model.live.debounce="search"
                     icon="o-magnifying-glass"
                     clearable />
        </div>

        @if($showFilters)
        {{-- BASIC FILTERS --}}
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input label="N° de Compte"
                     wire:model.live.debounce="accountNo"
                     placeholder="Ex: 123456" />

            <x-input label="ID Client"
                     wire:model.live.debounce="customerId"
                     placeholder="Ex: CUST001" />

            <x-select label="Statut"
                      :options="collect($accountStatusOptions)->map(fn($label, $value) => ['id' => $value, 'name' => $label])->values()"
                      wire:model.live="statusFilter"
                      placeholder="Tous les statuts"
                      placeholder-value="" />

            <x-select label="Devise"
                      :options="$accountStats['currencies']->map(fn($currency) => ['id' => $currency, 'name' => $currency])"
                      wire:model.live="currencyFilter"
                      placeholder="Toutes devises"
                      placeholder-value="" />
        </div>

        {{-- ADVANCED FILTERS --}}
        <div class="flex justify-center mb-4">
            <x-button label="{{ $showAdvancedFilters ? 'Masquer' : 'Afficher' }} filtres avancés"
                      icon="o-adjustments-horizontal"
                      wire:click="toggleAdvancedFilters"
                      class="btn-ghost btn-sm" />
        </div>

        @if($showAdvancedFilters)
        <div class="pt-4 border-t">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <x-input label="Nom Client"
                         wire:model.live.debounce="customerName"
                         placeholder="Nom ou nom public" />

                <x-select label="Type de Compte"
                          :options="$accountStats['account_types']->map(fn($type) => ['id' => $type, 'name' => 'Type ' . $type])"
                          wire:model.live="typeFilter"
                          placeholder="Tous les types"
                          placeholder-value="" />

                <x-select label="Filtre Solde"
                          :options="[
                              ['id' => 'positive', 'name' => 'Positif (>0)'],
                              ['id' => 'negative', 'name' => 'Négatif (<0)'],
                              ['id' => 'zero', 'name' => 'Nul (=0)'],
                              ['id' => 'high', 'name' => 'Élevé (>100k)'],
                              ['id' => 'very_high', 'name' => 'Très élevé (>1M)']
                          ]"
                          wire:model.live="balanceFilter"
                          placeholder="Tous soldes"
                          placeholder-value="" />

                <x-datepicker label="Période d'ouverture"
                              wire:model="dateRange"
                              icon="o-calendar"
                              :config="['mode' => 'range']" />
            </div>
        </div>
        @endif
        @endif
    </x-card>

    {{-- VIEW SELECTOR --}}
    <div class="flex items-center justify-between mb-4">
        <div class="tabs tabs-boxed">
            <button wire:click="setView('table')"
                    class="tab {{ $selectedView === 'table' ? 'tab-active' : '' }}">
                <x-icon name="o-table-cells" class="w-4 h-4 mr-2" />
                Tableau
            </button>
            <button wire:click="setView('cards')"
                    class="tab {{ $selectedView === 'cards' ? 'tab-active' : '' }}">
                <x-icon name="o-squares-2x2" class="w-4 h-4 mr-2" />
                Cartes
            </button>
            <button wire:click="setView('summary')"
                    class="tab {{ $selectedView === 'summary' ? 'tab-active' : '' }}">
                <x-icon name="o-chart-bar" class="w-4 h-4 mr-2" />
                Résumé
            </button>
        </div>

        {{-- BULK ACTIONS --}}
        @if(count($selectedAccounts) > 0)
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">{{ count($selectedAccounts) }} sélectionné(s)</span>
            <div class="dropdown dropdown-end">
                <label tabindex="0" class="btn btn-sm btn-outline">
                    Actions groupées
                    <x-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
                </label>
                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                    <li><a wire:click="bulkUpdateStatus('04')">Suspendre</a></li>
                    <li><a wire:click="bulkUpdateStatus('03')">Activer</a></li>
                    <li><a wire:click="bulkUpdateStatus('06')">Bloquer</a></li>
                    <li><hr></li>
                    <li><a wire:click="exportAccounts">Exporter sélection</a></li>
                </ul>
            </div>
        </div>
        @endif
    </div>

    {{-- CONTENT BASED ON VIEW --}}
    @if($selectedView === 'table')
        {{-- TABLE VIEW --}}
        <x-card title="Liste des Comptes" icon="o-table-cells">
            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr>
                            <th>
                                <x-checkbox wire:model.live="selectAll"
                                           wire:click="toggleSelectAll" />
                            </th>
                            <th>
                                <button wire:click="$set('sortBy', ['column' => 'account_no', 'direction' => '{{ $sortBy['direction'] === 'asc' ? 'desc' : 'asc' }}'])"
                                        class="flex items-center gap-1 hover:text-primary">
                                    N° Compte
                                    @if($sortBy['column'] === 'account_no')
                                        <x-icon name="o-chevron-{{ $sortBy['direction'] === 'asc' ? 'up' : 'down' }}" class="w-4 h-4" />
                                    @endif
                                </button>
                            </th>
                            <th>Client</th>
                            <th>Nom/Alias</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th class="text-right">Solde</th>
                            <th>Devise</th>
                            <th>Date ouverture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                        <tr class="hover">
                            <td>
                                <x-checkbox wire:model.live="selectedAccounts"
                                           value="{{ $account->account_no }}" />
                            </td>
                            <td class="font-mono">
                                <a href="{{ route('customer.accounts.show', $account->account_no) }}"
                                   class="link link-primary">
                                    {{ $account->account_no }}
                                </a>
                                @if($account->default_flag === 'Y')
                                    <x-badge value="Défaut" class="ml-1 badge-primary badge-xs" />
                                @endif
                            </td>
                            <td>
                                @if($account->customer)
                                    <div>
                                        <div class="font-medium">
                                            <a href="/customers/{{ $account->customer->customer_id }}"
                                               class="link">
                                                {{ $account->customer->public_name ?: $account->customer->user_name }}
                                            </a>
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">{{ $account->customer->customer_id }}</div>
                                    </div>
                                @else
                                    <span class="text-gray-400">Client non trouvé</span>
                                @endif
                            </td>
                            <td>
                                <div>
                                    <div class="font-medium">{{ $account->account_name ?: '-' }}</div>
                                    @if($account->alias)
                                        <div class="text-xs text-gray-500">{{ $account->alias }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <x-badge value="Type {{ $account->account_type_id }}" class="badge-outline" />
                            </td>
                            <td>
                                @php
                                    $statusClass = match($account->account_status) {
                                        '03' => 'badge-success',
                                        '04', '06' => 'badge-error',
                                        '01', '02' => 'badge-warning',
                                        '05' => 'badge-neutral',
                                        '07' => 'badge-ghost',
                                        default => 'badge-neutral'
                                    };
                                @endphp
                                <x-badge value="{{ $accountStatusOptions[$account->account_status] ?? $account->account_status }}"
                                         class="{{ $statusClass }}" />
                            </td>
                            <td class="font-mono text-right">
                                <div class="{{ $account->balance > 0 ? 'text-success' : ($account->balance < 0 ? 'text-error' : 'text-gray-500') }}">
                                    {{ number_format($account->balance ?? 0, 2) }}
                                </div>
                            </td>
                            <td class="font-semibold">{{ $account->currency ?? 'EUR' }}</td>
                            <td>
                                {{ $account->open_date ? \Carbon\Carbon::parse($account->open_date)->format('d/m/Y') : '-' }}
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <x-button icon="o-eye"
                                              link="{{ route('customer.accounts.show', $account->account_no) }}"
                                              class="btn-ghost btn-xs"
                                              tooltip="Détails" />
                                    <x-button icon="o-user"
                                              link="/customers/{{ $account->customer->customer_id ?? '' }}"
                                              class="btn-ghost btn-xs"
                                              tooltip="Client" />
                                    <x-button icon="o-eye"
                                              class="btn-ghost btn-xs"
                                              tooltip="Transactions" />
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <x-icon name="o-credit-card" class="w-16 h-16 text-gray-300" />
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-500">Aucun compte trouvé</h3>
                                        <p class="text-gray-400">
                                            @if($filterCount > 0)
                                                Aucun compte ne correspond aux critères de recherche.
                                            @else
                                                Aucun compte n'est disponible.
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $accounts->links() }}
            </div>
        </x-card>

    @elseif($selectedView === 'cards')
        {{-- CARDS VIEW --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse($accounts as $account)
            <x-card>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="font-mono text-lg font-bold">{{ $account->account_no }}</h3>
                        <p class="text-sm text-gray-500">{{ $account->account_name ?: $account->alias ?: 'Sans nom' }}</p>
                    </div>
                    <x-checkbox wire:model.live="selectedAccounts"
                               value="{{ $account->account_no }}" />
                </div>

                <div class="mb-4 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm">Client:</span>
                        <span class="font-medium">{{ $account->customer->public_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Solde:</span>
                        <span class="font-mono font-bold {{ $account->balance > 0 ? 'text-success' : ($account->balance < 0 ? 'text-error' : '') }}">
                            {{ number_format($account->balance ?? 0, 2) }} {{ $account->currency }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Statut:</span>
                        @php
                            $statusClass = match($account->account_status) {
                                '03' => 'badge-success',
                                '04', '06' => 'badge-error',
                                '01', '02' => 'badge-warning',
                                '05' => 'badge-neutral',
                                default => 'badge-neutral'
                            };
                        @endphp
                        <x-badge value="{{ $accountStatusOptions[$account->account_status] ?? $account->account_status }}"
                                 class="{{ $statusClass }} badge-sm" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-button label="Voir"
                              icon="o-eye"
                              link="{{ route('customer.accounts.show', $account->account_no) }}"
                              class="flex-1 btn-sm btn-outline" />
                    <x-button label="Client"
                              icon="o-user"
                              link="/customers/{{ $account->customer->customer_id ?? '' }}"
                              class="btn-sm btn-ghost" />
                </div>
            </x-card>
            @empty
            <div class="py-12 text-center col-span-full">
                <x-icon name="o-credit-card" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                <h3 class="text-lg font-semibold text-gray-500">Aucun compte trouvé</h3>
            </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $accounts->links() }}
        </div>

    @else
        {{-- SUMMARY VIEW --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Répartition par Statut" icon="o-chart-pie">
                <div class="space-y-3">
                    @foreach($accountStatusOptions as $status => $label)
                        @php
                            $count = $accounts->where('account_status', $status)->count();
                            $percentage = $accounts->count() > 0 ? ($count / $accounts->count()) * 100 : 0;
                        @endphp
                        @if($count > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-sm">{{ $label }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full">
                                    <div class="h-2 rounded-full bg-primary" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="font-mono text-sm">{{ $count }}</span>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </x-card>

            <x-card title="Répartition par Devise" icon="o-currency-euro">
                <div class="space-y-3">
                    @foreach($accountStats['currencies'] as $currency)
                        @php
                            $count = $accounts->where('currency', $currency)->count();
                            $totalBalance = $accounts->where('currency', $currency)->sum('balance');
                            $percentage = $accounts->count() > 0 ? ($count / $accounts->count()) * 100 : 0;
                        @endphp
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-semibold">{{ $currency }}</span>
                                <div class="text-xs text-gray-500">{{ number_format($totalBalance, 2) }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full">
                                    <div class="h-2 rounded-full bg-success" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="font-mono text-sm">{{ $count }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Analyse des Soldes" icon="o-chart-bar">
                <div class="space-y-4">
                    @php
                        $balanceRanges = [
                            ['label' => 'Négatif', 'condition' => fn($balance) => $balance < 0, 'class' => 'text-error'],
                            ['label' => '0€', 'condition' => fn($balance) => $balance == 0, 'class' => 'text-gray-500'],
                            ['label' => '0-1k€', 'condition' => fn($balance) => $balance > 0 && $balance <= 1000, 'class' => 'text-info'],
                            ['label' => '1k-10k€', 'condition' => fn($balance) => $balance > 1000 && $balance <= 10000, 'class' => 'text-primary'],
                            ['label' => '10k-100k€', 'condition' => fn($balance) => $balance > 10000 && $balance <= 100000, 'class' => 'text-warning'],
                            ['label' => '+100k€', 'condition' => fn($balance) => $balance > 100000, 'class' => 'text-success']
                        ];
                    @endphp

                    @foreach($balanceRanges as $range)
                        @php
                            $count = $accounts->filter(fn($account) => $range['condition']($account->balance ?? 0))->count();
                            $percentage = $accounts->count() > 0 ? ($count / $accounts->count()) * 100 : 0;
                        @endphp
                        @if($count > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-sm {{ $range['class'] }}">{{ $range['label'] }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full">
                                    <div class="bg-current h-2 rounded-full {{ $range['class'] }}" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="font-mono text-sm">{{ $count }}</span>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </x-card>

            <x-card title="Top Clients par Solde" icon="o-trophy">
                <div class="space-y-3">
                    @php
                        $topAccounts = $accounts->sortByDesc('balance')->take(5);
                    @endphp
                    @foreach($topAccounts as $index => $account)
                    <div class="flex justify-between items-center p-2 rounded {{ $index === 0 ? 'bg-warning/10' : 'bg-base-200' }}">
                        <div class="flex items-center gap-2">
                            @if($index === 0)
                                <x-icon name="o-trophy" class="w-4 h-4 text-warning" />
                            @else
                                <span class="w-4 text-sm text-center text-gray-500">{{ $index + 1 }}</span>
                            @endif
                            <div>
                                <div class="font-medium">{{ $account->customer->public_name ?? 'N/A' }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ $account->account_no }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold {{ $account->balance > 100000 ? 'text-success' : 'text-primary' }}">
                                {{ number_format($account->balance ?? 0, 0) }}€
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif
</div>
