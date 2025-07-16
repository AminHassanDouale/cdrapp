<?php

use App\Models\Customer;
use App\Models\CustomerAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    use WithPagination;

    public Customer $customer;

    // Filters
    public string $search = '';
    public string $statusFilter = '';
    public string $currencyFilter = '';
    public string $typeFilter = '';
    public string $balanceFilter = '';
    public array $sortBy = ['column' => 'open_date', 'direction' => 'desc'];

    // UI State
    public bool $showFilters = true;
    public string $selectedAccount = '';
    public bool $showAccountDetails = false;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function clearFilters()
    {
        $this->reset([
            'search',
            'statusFilter',
            'currencyFilter',
            'typeFilter',
            'balanceFilter'
        ]);
        $this->resetPage();
    }

    public function showAccount(string $accountNo)
    {
        $this->selectedAccount = $accountNo;
        $this->showAccountDetails = true;
    }

    public function closeAccountDetails()
    {
        $this->showAccountDetails = false;
        $this->selectedAccount = '';
    }

    public function filterCount(): int
    {
        return collect([
            $this->search,
            $this->statusFilter,
            $this->currencyFilter,
            $this->typeFilter,
            $this->balanceFilter
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function accounts()
    {
        $query = $this->customer->accounts();

        // Apply search
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('account_no', 'like', "%{$this->search}%")
                  ->orWhere('alias', 'like', "%{$this->search}%")
                  ->orWhere('account_name', 'like', "%{$this->search}%");
            });
        }

        // Apply filters
        if (!empty($this->statusFilter)) {
            $query->where('account_status', $this->statusFilter);
        }

        if (!empty($this->currencyFilter)) {
            $query->where('currency', $this->currencyFilter);
        }

        if (!empty($this->typeFilter)) {
            $query->where('account_type_id', $this->typeFilter);
        }

        // Balance filter
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
                $query->where('balance', '>', 10000);
                break;
        }

        return $query->orderBy(...array_values($this->sortBy))->paginate(15);
    }

    public function getAccountStatsProperty()
    {
        $accounts = $this->customer->accounts;

        return [
            'total' => $accounts->count(),
            'active' => $accounts->where('account_status', '03')->count(),
            'inactive' => $accounts->where('account_status', '!=', '03')->count(),
            'with_balance' => $accounts->where('balance', '>', 0)->count(),
            'total_balance' => $accounts->sum('balance'),
            'total_reserved' => $accounts->sum('reserved_balance'),
            'currencies' => $accounts->pluck('currency')->unique()->filter()->values(),
            'account_types' => $accounts->pluck('account_type_id')->unique()->filter()->values(),
            'avg_balance' => $accounts->count() > 0 ? $accounts->avg('balance') : 0
        ];
    }

    public function getSelectedAccountDetailsProperty()
    {
        if (empty($this->selectedAccount)) {
            return null;
        }

        return CustomerAccount::where('account_no', $this->selectedAccount)->first();
    }

    public function getAccountStatusOptionsProperty()
    {
        return [
            '01' => 'En attente',
            '02' => 'En cours d\'ouverture',
            '03' => 'Actif',
            '04' => 'Suspendu',
            '05' => 'Fermé',
            '06' => 'Bloqué'
        ];
    }

    public function createNewAccount()
    {
        // Logic to create new account
        $this->dispatch('create-account');
    }

    public function blockAccount(string $accountNo)
    {
        // Logic to block account
        $this->dispatch('block-account', accountNo: $accountNo);
    }

    public function unblockAccount(string $accountNo)
    {
        // Logic to unblock account
        $this->dispatch('unblock-account', accountNo: $accountNo);
    }

    public function with(): array
    {
        return [
            'customer' => $this->customer,
            'accounts' => $this->accounts(),
            'accountStats' => $this->account_stats,
            'selectedAccountDetails' => $this->selected_account_details,
            'accountStatusOptions' => $this->account_status_options,
            'filterCount' => $this->filterCount()
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Comptes - {{ $customer->public_name ?? $customer->user_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-badge value="{{ $accountStats['active'] }}/{{ $accountStats['total'] }} actifs" class="badge-info" />
                <x-badge value="{{ number_format($accountStats['total_balance'], 0) }}€" class="badge-success" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Retour Client" icon="o-arrow-left"
                      link="/customers/{{ $customer->customer_id }}"
                      class="btn-outline" />
            <x-button label="KYC" icon="o-identification"
                      link="/customers/{{ $customer->customer_id }}/kyc"
                      class="btn-outline" />
            <x-button label="Nouveau Compte" icon="o-plus"
                      wire:click="createNewAccount"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- STATISTICS CARDS --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-credit-card" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Comptes</div>
                <div class="stat-value text-primary">{{ $accountStats['total'] }}</div>
                <div class="stat-desc">{{ $accountStats['active'] }} actifs, {{ $accountStats['inactive'] }} inactifs</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Total</div>
                <div class="stat-value text-success">{{ number_format($accountStats['total_balance'], 2) }}€</div>
                <div class="stat-desc">Moyenne: {{ number_format($accountStats['avg_balance'], 2) }}€</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-lock-closed" class="w-8 h-8" />
                </div>
                <div class="stat-title">Montant Réservé</div>
                <div class="stat-value text-warning">{{ number_format($accountStats['total_reserved'], 2) }}€</div>
                <div class="stat-desc">{{ $accountStats['with_balance'] }} comptes avec solde</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-currency-euro" class="w-8 h-8" />
                </div>
                <div class="stat-title">Devises</div>
                <div class="stat-value text-info">{{ $accountStats['currencies']->count() }}</div>
                <div class="stat-desc">{{ $accountStats['currencies']->join(', ') ?: 'EUR' }}</div>
            </div>
        </div>
    </div>

    {{-- FILTERS --}}
    <x-card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Filtres et Recherche</h3>
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

        {{-- SEARCH BAR --}}
        <div class="mb-4">
            <x-input placeholder="Rechercher par numéro de compte, alias ou nom..."
                     wire:model.live.debounce="search"
                     icon="o-magnifying-glass"
                     clearable />
        </div>

        @if($showFilters)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
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

            <x-select label="Type de compte"
                      :options="$accountStats['account_types']->map(fn($type) => ['id' => $type, 'name' => 'Type ' . $type])"
                      wire:model.live="typeFilter"
                      placeholder="Tous les types"
                      placeholder-value="" />

            <x-select label="Solde"
                      :options="[
                          ['id' => 'positive', 'name' => 'Positif (>0)'],
                          ['id' => 'negative', 'name' => 'Négatif (<0)'],
                          ['id' => 'zero', 'name' => 'Nul (=0)'],
                          ['id' => 'high', 'name' => 'Élevé (>10k)']
                      ]"
                      wire:model.live="balanceFilter"
                      placeholder="Tous soldes"
                      placeholder-value="" />

            <div class="flex items-end">
                <x-button label="Exporter" icon="o-arrow-down-tray" class="w-full btn-outline" />
            </div>
        </div>
        @endif
    </x-card>

    {{-- ACCOUNTS TABLE --}}
    <x-card title="Liste des Comptes" icon="o-banknotes">
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra">
                <thead>
                    <tr>
                        <th>
                            <button wire:click="$set('sortBy', ['column' => 'account_no', 'direction' => '{{ $sortBy['direction'] === 'asc' ? 'desc' : 'asc' }}'])"
                                    class="flex items-center gap-1 hover:text-primary">
                                N° Compte
                                @if($sortBy['column'] === 'account_no')
                                    <x-icon name="o-chevron-{{ $sortBy['direction'] === 'asc' ? 'up' : 'down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th>Nom/Alias</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th class="text-right">Solde</th>
                        <th class="text-right">Réservé</th>
                        <th>Devise</th>
                        <th>
                            <button wire:click="$set('sortBy', ['column' => 'open_date', 'direction' => '{{ $sortBy['direction'] === 'asc' ? 'desc' : 'asc' }}'])"
                                    class="flex items-center gap-1 hover:text-primary">
                                Date ouverture
                                @if($sortBy['column'] === 'open_date')
                                    <x-icon name="o-chevron-{{ $sortBy['direction'] === 'asc' ? 'up' : 'down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr class="cursor-pointer hover" wire:click="showAccount('{{ $account->account_no }}')">
                        <td class="font-mono">
                            <div class="flex items-center gap-2">
                                {{ $account->account_no }}
                                @if($account->default_flag === 'Y')
                                    <x-badge value="Défaut" class="badge-primary badge-xs" />
                                @endif
                            </div>
                        </td>
                        <td>
                            <div>
                                <div class="font-medium">{{ $account->account_name ?: ($account->alias ?: '-') }}</div>
                                @if($account->account_name && $account->alias)
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
                        <td class="font-mono text-right">
                            @if($account->reserved_balance > 0)
                                <span class="text-warning">{{ number_format($account->reserved_balance, 2) }}</span>
                            @else
                                <span class="text-gray-400">0.00</span>
                            @endif
                        </td>
                        <td class="font-semibold">{{ $account->currency ?? 'EUR' }}</td>
                        <td>
                            {{ $account->open_date ? \Carbon\Carbon::parse($account->open_date)->format('d/m/Y') : '-' }}
                        </td>
                        <td>
                            <div class="flex gap-1" onclick="event.stopPropagation()">
                                <x-button icon="o-eye"
                                          wire:click="showAccount('{{ $account->account_no }}')"
                                          class="btn-ghost btn-xs"
                                          tooltip="Détails" />
                                <x-button icon="o-eye"
                                          class="btn-ghost btn-xs"
                                          tooltip="Transactions" />
                                @if($account->account_status === '03')
                                    <x-button icon="o-no-symbol"
                                              wire:click="blockAccount('{{ $account->account_no }}')"
                                              class="btn-ghost btn-xs text-error"
                                              tooltip="Bloquer" />
                                @elseif(in_array($account->account_status, ['04', '06']))
                                    <x-button icon="o-check-circle"
                                              wire:click="unblockAccount('{{ $account->account_no }}')"
                                              class="btn-ghost btn-xs text-success"
                                              tooltip="Débloquer" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <x-icon name="o-credit-card" class="w-16 h-16 text-gray-300" />
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-500">Aucun compte trouvé</h3>
                                    <p class="text-gray-400">
                                        @if($filterCount > 0)
                                            Aucun compte ne correspond aux filtres appliqués.
                                        @else
                                            Ce client n'a pas encore de compte.
                                        @endif
                                    </p>
                                </div>
                                @if($filterCount === 0)
                                    <x-button label="Créer le premier compte"
                                              icon="o-plus"
                                              wire:click="createNewAccount"
                                              class="btn-primary" />
                                @endif
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

    {{-- ACCOUNT DETAILS MODAL --}}
    @if($showAccountDetails && $selectedAccountDetails)
    <div class="modal modal-open">
        <div class="w-11/12 max-w-4xl modal-box">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold">Détails du Compte {{ $selectedAccountDetails->account_no }}</h3>
                <button wire:click="closeAccountDetails" class="btn btn-sm btn-circle btn-ghost">✕</button>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- ACCOUNT INFO --}}
                <x-card title="Informations du Compte" icon="o-credit-card">
                    <div class="space-y-3">
                        <x-input label="Numéro de compte" value="{{ $selectedAccountDetails->account_no }}" readonly />
                        <x-input label="Nom du compte" value="{{ $selectedAccountDetails->account_name }}" readonly />
                        <x-input label="Alias" value="{{ $selectedAccountDetails->alias }}" readonly />
                        <x-input label="Type" value="Type {{ $selectedAccountDetails->account_type_id }}" readonly />
                        <x-input label="Devise" value="{{ $selectedAccountDetails->currency }}" readonly />
                        <div class="flex gap-2">
                            <x-checkbox label="Compte par défaut" :checked="$selectedAccountDetails->default_flag === 'Y'" disabled />
                            <x-checkbox label="Digest activé" :checked="$selectedAccountDetails->digest_flag === 'Y'" disabled />
                        </div>
                    </div>
                </x-card>

                {{-- BALANCE INFO --}}
                <x-card title="Informations Financières" icon="o-banknotes">
                    <div class="space-y-3">
                        <div class="stat">
                            <div class="stat-title">Solde Disponible</div>
                            <div class="stat-value text-{{ $selectedAccountDetails->balance > 0 ? 'success' : ($selectedAccountDetails->balance < 0 ? 'error' : 'gray-500') }}">
                                {{ number_format($selectedAccountDetails->balance ?? 0, 2) }} {{ $selectedAccountDetails->currency }}
                            </div>
                        </div>

                        <div class="stat">
                            <div class="stat-title">Montant Réservé</div>
                            <div class="stat-value text-warning">
                                {{ number_format($selectedAccountDetails->reserved_balance ?? 0, 2) }} {{ $selectedAccountDetails->currency }}
                            </div>
                        </div>

                        <div class="stat">
                            <div class="stat-title">Solde Non Éclairci</div>
                            <div class="stat-value text-info">
                                {{ number_format($selectedAccountDetails->unclear_balance ?? 0, 2) }} {{ $selectedAccountDetails->currency }}
                            </div>
                        </div>
                    </div>
                </x-card>

                {{-- DATES --}}
                <x-card title="Historique" icon="o-clock">
                    <div class="space-y-3">
                        <x-input label="Date d'ouverture"
                                 value="{{ $selectedAccountDetails->open_date ? \Carbon\Carbon::parse($selectedAccountDetails->open_date)->format('d/m/Y H:i') : '-' }}"
                                 readonly />
                        <x-input label="Date de fermeture"
                                 value="{{ $selectedAccountDetails->close_date ? \Carbon\Carbon::parse($selectedAccountDetails->close_date)->format('d/m/Y H:i') : '-' }}"
                                 readonly />
                        <x-input label="Dernière activité"
                                 value="{{ $selectedAccountDetails->last_date ? \Carbon\Carbon::parse($selectedAccountDetails->last_date)->format('d/m/Y H:i') : '-' }}"
                                 readonly />
                        <x-input label="Changement statut"
                                 value="{{ $selectedAccountDetails->status_last_date ? \Carbon\Carbon::parse($selectedAccountDetails->status_last_date)->format('d/m/Y H:i') : '-' }}"
                                 readonly />
                    </div>
                </x-card>

                {{-- ACTIONS --}}
                <x-card title="Actions" icon="o-cog">
                    <div class="space-y-2">
                        <x-button label="Voir Transactions" icon="o-eye" class="w-full btn-primary" />
                        <x-button label="Historique Complet" icon="o-clock" class="w-full btn-outline" />
                        <x-button label="Modifier Compte" icon="o-pencil" class="w-full btn-outline" />
                        <div class="divider"></div>
                        @if($selectedAccountDetails->account_status === '03')
                            <x-button label="Suspendre Compte"
                                      icon="o-pause"
                                      wire:click="blockAccount('{{ $selectedAccountDetails->account_no }}')"
                                      class="w-full btn-warning" />
                            <x-button label="Fermer Compte"
                                      icon="o-x-circle"
                                      class="w-full btn-error" />
                        @elseif(in_array($selectedAccountDetails->account_status, ['04', '06']))
                            <x-button label="Réactiver Compte"
                                      icon="o-check-circle"
                                      wire:click="unblockAccount('{{ $selectedAccountDetails->account_no }}')"
                                      class="w-full btn-success" />
                        @endif
                    </div>
                </x-card>
            </div>
        </div>
    </div>
    @endif
</div>
