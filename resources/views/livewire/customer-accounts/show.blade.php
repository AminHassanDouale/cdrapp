<?php

use App\Models\CustomerAccount;
use App\Models\Customer;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public CustomerAccount $account;
    public string $activeTab = 'details';

    // Account management
    public bool $showEditModal = false;
    public bool $showStatusModal = false;
    public string $newStatus = '';
    public string $statusReason = '';

    // Transaction filters
    public string $transactionDateRange = '';
    public string $transactionType = '';
    public string $transactionStatus = '';

    public function mount(CustomerAccount $account)
    {
        $this->account = $account->load('customer');
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function showEditModal()
    {
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
    }

    public function showStatusModal()
    {
        $this->showStatusModal = true;
    }

    public function closeStatusModal()
    {
        $this->showStatusModal = false;
        $this->newStatus = '';
        $this->statusReason = '';
    }

    public function updateAccountStatus()
    {
        if (empty($this->newStatus)) {
            $this->dispatch('notify', message: 'Veuillez sélectionner un statut', type: 'error');
            return;
        }

        // Logic to update account status
        $this->dispatch('update-account-status',
            accountNo: $this->account->account_no,
            status: $this->newStatus,
            reason: $this->statusReason
        );

        $this->closeStatusModal();
    }

    public function blockAccount()
    {
        $this->newStatus = '06';
        $this->statusReason = 'Blocage administratif';
        $this->updateAccountStatus();
    }

    public function unblockAccount()
    {
        $this->newStatus = '03';
        $this->statusReason = 'Déblocage administratif';
        $this->updateAccountStatus();
    }

    public function closeAccount()
    {
        $this->newStatus = '05';
        $this->showStatusModal = true;
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

    public function getAccountStatsProperty()
    {
        // This would typically come from a transaction service/repository
        return [
            'total_transactions' => 1250,
            'last_30_days_transactions' => 87,
            'average_monthly_balance' => 15750.50,
            'highest_balance' => 25000.00,
            'lowest_balance' => 2500.00,
            'last_transaction_date' => '2025-07-10',
            'days_since_last_transaction' => 1
        ];
    }

    public function getAccountHealthProperty()
    {
        $balance = $this->account->balance ?? 0;
        $reservedBalance = $this->account->reserved_balance ?? 0;
        $unclearBalance = $this->account->unclear_balance ?? 0;
        $stats = $this->account_stats;

        $score = 100;

        // Negative balance penalty
        if ($balance < 0) $score -= 30;

        // High reserved balance penalty
        if ($reservedBalance > ($balance * 0.5)) $score -= 15;

        // Unclear balance penalty
        if ($unclearBalance > 0) $score -= 10;

        // Inactivity penalty
        if ($stats['days_since_last_transaction'] > 30) $score -= 20;

        // Account status penalty
        if (!in_array($this->account->account_status, ['03'])) $score -= 25;

        $score = max(0, min(100, $score));

        if ($score >= 80) return ['score' => $score, 'level' => 'Excellent', 'class' => 'text-success'];
        if ($score >= 60) return ['score' => $score, 'level' => 'Bon', 'class' => 'text-primary'];
        if ($score >= 40) return ['score' => $score, 'level' => 'Moyen', 'class' => 'text-warning'];
        return ['score' => $score, 'level' => 'Critique', 'class' => 'text-error'];
    }

    public function getRecentTransactionsProperty()
    {
        // Mock transaction data - in real app, this would come from transaction service
        return collect([
            [
                'id' => 'TXN001',
                'date' => '2025-07-11 09:30:00',
                'type' => 'Virement entrant',
                'amount' => 1500.00,
                'balance_after' => 15750.50,
                'description' => 'Salaire mensuel',
                'status' => 'Complété'
            ],
            [
                'id' => 'TXN002',
                'date' => '2025-07-10 14:22:00',
                'type' => 'Paiement',
                'amount' => -85.30,
                'balance_after' => 14250.50,
                'description' => 'Achat en ligne',
                'status' => 'Complété'
            ],
            [
                'id' => 'TXN003',
                'date' => '2025-07-09 11:15:00',
                'type' => 'Retrait DAB',
                'amount' => -200.00,
                'balance_after' => 14335.80,
                'description' => 'Retrait espèces',
                'status' => 'Complété'
            ],
            [
                'id' => 'TXN004',
                'date' => '2025-07-08 16:45:00',
                'type' => 'Virement sortant',
                'amount' => -500.00,
                'balance_after' => 14535.80,
                'description' => 'Loyer juillet',
                'status' => 'En cours'
            ]
        ]);
    }

    public function with(): array
    {
        return [
            'account' => $this->account,
            'accountStatusOptions' => $this->account_status_options,
            'accountStats' => $this->account_stats,
            'accountHealth' => $this->account_health,
            'recentTransactions' => $this->recent_transactions
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Compte {{ $account->account_no }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
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
                <x-badge value="{{ number_format($account->balance ?? 0, 2) }}{{ $account->currency }}"
                         class="badge-{{ ($account->balance ?? 0) > 0 ? 'success' : (($account->balance ?? 0) < 0 ? 'error' : 'neutral') }}" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Retour Liste"
                      icon="o-arrow-left"
                      link="{{ route('customer.accounts.index') }}"
                      class="btn-outline" />
            <x-button label="Voir Client"
                      icon="o-user"
                      link="/customers/{{ $account->customer->customer_id ?? '' }}"
                      class="btn-outline" />
            @can('customer-accounts.edit')
                <x-button label="Modifier"
                          icon="o-pencil"
                          wire:click="showEditModal"
                          class="btn-primary" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- ACCOUNT OVERVIEW CARDS --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-{{ ($account->balance ?? 0) > 0 ? 'success' : (($account->balance ?? 0) < 0 ? 'error' : 'neutral') }}">
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Disponible</div>
                <div class="stat-value text-{{ ($account->balance ?? 0) > 0 ? 'success' : (($account->balance ?? 0) < 0 ? 'error' : 'neutral') }}">
                    {{ number_format($account->balance ?? 0, 2) }}
                </div>
                <div class="stat-desc">{{ $account->currency ?? 'EUR' }}</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-lock-closed" class="w-8 h-8" />
                </div>
                <div class="stat-title">Montant Réservé</div>
                <div class="stat-value text-warning">{{ number_format($account->reserved_balance ?? 0, 2) }}</div>
                <div class="stat-desc">{{ $account->currency ?? 'EUR' }}</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure text-info">
                    <x-icon name="o-eye" class="w-8 h-8" />
                </div>
                <div class="stat-title">Transactions (30j)</div>
                <div class="stat-value text-info">{{ $accountStats['last_30_days_transactions'] }}</div>
                <div class="stat-desc">Total: {{ $accountStats['total_transactions'] }}</div>
            </div>
        </div>

        <div class="shadow stats">
            <div class="stat">
                <div class="stat-figure {{ $accountHealth['class'] }}">
                    <x-icon name="o-heart" class="w-8 h-8" />
                </div>
                <div class="stat-title">Santé du Compte</div>
                <div class="stat-value {{ $accountHealth['class'] }}">{{ $accountHealth['score'] }}%</div>
                <div class="stat-desc">{{ $accountHealth['level'] }}</div>
            </div>
        </div>
    </div>

    {{-- TABS NAVIGATION --}}
    <div class="mb-6">
        <div class="tabs tabs-boxed">
            <button wire:click="setTab('details')"
                    class="tab {{ $activeTab === 'details' ? 'tab-active' : '' }}">
                <x-icon name="o-information-circle" class="w-4 h-4 mr-2" />
                Détails
            </button>
            <button wire:click="setTab('transactions')"
                    class="tab {{ $activeTab === 'transactions' ? 'tab-active' : '' }}">
                <x-icon name="o-eye" class="w-4 h-4 mr-2" />
                Transactions
            </button>
            <button wire:click="setTab('history')"
                    class="tab {{ $activeTab === 'history' ? 'tab-active' : '' }}">
                <x-icon name="o-clock" class="w-4 h-4 mr-2" />
                Historique
            </button>
            <button wire:click="setTab('analytics')"
                    class="tab {{ $activeTab === 'analytics' ? 'tab-active' : '' }}">
                <x-icon name="o-chart-bar" class="w-4 h-4 mr-2" />
                Analyse
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- MAIN CONTENT --}}
        <div class="lg:col-span-2">
            @if($activeTab === 'details')
                {{-- ACCOUNT DETAILS --}}
                <div class="space-y-6">
                    <x-card title="Informations du Compte" icon="o-credit-card">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-input label="Numéro de compte" value="{{ $account->account_no }}" readonly />
                            <x-input label="Nom du compte" value="{{ $account->account_name ?: '-' }}" readonly />
                            <x-input label="Alias" value="{{ $account->alias ?: '-' }}" readonly />
                            <x-input label="Type de compte" value="Type {{ $account->account_type_id }}" readonly />
                            <x-input label="Devise" value="{{ $account->currency ?? 'EUR' }}" readonly />
                            <x-input label="Statut" value="{{ $accountStatusOptions[$account->account_status] ?? $account->account_status }}" readonly />
                        </div>

                        <div class="flex gap-4 mt-4">
                            <x-checkbox label="Compte par défaut"
                                       :checked="$account->default_flag === 'Y'"
                                       disabled />
                            <x-checkbox label="Digest activé"
                                       :checked="$account->digest_flag === 'Y'"
                                       disabled />
                        </div>
                    </x-card>

                    <x-card title="Informations Financières" icon="o-banknotes">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Solde Disponible</div>
                                <div class="stat-value text-{{ ($account->balance ?? 0) > 0 ? 'success' : (($account->balance ?? 0) < 0 ? 'error' : 'neutral') }}">
                                    {{ number_format($account->balance ?? 0, 2) }}
                                </div>
                                <div class="stat-desc">{{ $account->currency ?? 'EUR' }}</div>
                            </div>

                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Montant Réservé</div>
                                <div class="stat-value text-warning">
                                    {{ number_format($account->reserved_balance ?? 0, 2) }}
                                </div>
                                <div class="stat-desc">{{ $account->currency ?? 'EUR' }}</div>
                            </div>

                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Solde Non Éclairci</div>
                                <div class="stat-value text-info">
                                    {{ number_format($account->unclear_balance ?? 0, 2) }}
                                </div>
                                <div class="stat-desc">{{ $account->currency ?? 'EUR' }}</div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="mb-3 font-semibold">Historique des Soldes</h4>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <x-input label="Solde moyen (30j)"
                                         value="{{ number_format($accountStats['average_monthly_balance'], 2) }} {{ $account->currency }}"
                                         readonly />
                                <x-input label="Dernier solde connu"
                                         value="{{ number_format($accountStats['last_date_balance'] ?? 0, 2) }} {{ $account->currency }}"
                                         readonly />
                                <x-input label="Solde le plus haut"
                                         value="{{ number_format($accountStats['highest_balance'], 2) }} {{ $account->currency }}"
                                         readonly />
                                <x-input label="Solde le plus bas"
                                         value="{{ number_format($accountStats['lowest_balance'], 2) }} {{ $account->currency }}"
                                         readonly />
                            </div>
                        </div>
                    </x-card>

                    <x-card title="Informations Client" icon="o-user">
                        @if($account->customer)
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-input label="ID Client" value="{{ $account->customer->customer_id }}" readonly />
                            <x-input label="Nom public" value="{{ $account->customer->public_name ?: '-' }}" readonly />
                            <x-input label="Nom utilisateur" value="{{ $account->customer->user_name ?: '-' }}" readonly />
                            <x-input label="Type client" value="{{ $account->customer->customer_type ?: '-' }}" readonly />
                            <x-input label="Niveau de confiance" value="{{ $account->customer->trust_level }}/5" readonly />
                            <x-input label="Statut client" value="{{ $account->customer->status }}" readonly />
                        </div>

                        <div class="mt-4">
                            <x-button label="Voir Profil Client"
                                      icon="o-user"
                                      link="/customers/{{ $account->customer->customer_id }}"
                                      class="btn-outline" />
                        </div>
                        @else
                        <div class="py-8 text-center">
                            <x-icon name="o-exclamation-triangle" class="w-12 h-12 mx-auto mb-4 text-warning" />
                            <h3 class="font-semibold text-warning">Client non trouvé</h3>
                            <p class="text-gray-600">Les informations client ne sont pas disponibles pour ce compte.</p>
                        </div>
                        @endif
                    </x-card>
                </div>

            @elseif($activeTab === 'transactions')
                {{-- TRANSACTIONS TAB --}}
                <x-card title="Transactions Récentes" icon="o-eye">
                    <div class="overflow-x-auto">
                        <table class="table table-sm table-zebra">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="text-right">Montant</th>
                                    <th class="text-right">Solde après</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $transaction)
                                <tr class="hover">
                                    <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <x-badge value="{{ $transaction['type'] }}" class="badge-outline" />
                                    </td>
                                    <td>{{ $transaction['description'] }}</td>
                                    <td class="text-right font-mono {{ $transaction['amount'] > 0 ? 'text-success' : 'text-error' }}">
                                        {{ $transaction['amount'] > 0 ? '+' : '' }}{{ number_format($transaction['amount'], 2) }}
                                    </td>
                                    <td class="font-mono text-right">{{ number_format($transaction['balance_after'], 2) }}</td>
                                    <td>
                                        <x-badge value="{{ $transaction['status'] }}"
                                                 class="badge-{{ $transaction['status'] === 'Complété' ? 'success' : 'warning' }}" />
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-center">
                        <x-button label="Voir Toutes les Transactions"
                                  icon="o-arrow-right"
                                  class="btn-outline" />
                    </div>
                </x-card>

            @elseif($activeTab === 'history')
                {{-- HISTORY TAB --}}
                <x-card title="Historique du Compte" icon="o-clock">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded bg-base-200">
                            <div class="flex items-center space-x-3">
                                <x-icon name="o-plus-circle" class="w-5 h-5 text-success" />
                                <div>
                                    <div class="font-medium">Ouverture du compte</div>
                                    <div class="text-sm text-gray-500">Compte créé et activé</div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">
                                {{ $account->open_date ? \Carbon\Carbon::parse($account->open_date)->format('d/m/Y H:i') : '-' }}
                            </span>
                        </div>

                        @if($account->last_date)
                        <div class="flex items-center justify-between p-3 rounded bg-base-200">
                            <div class="flex items-center space-x-3">
                                <x-icon name="o-eye" class="w-5 h-5 text-info" />
                                <div>
                                    <div class="font-medium">Dernière activité</div>
                                    <div class="text-sm text-gray-500">Transaction ou consultation</div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($account->last_date)->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        @endif

                        @if($account->status_last_date)
                        <div class="flex items-center justify-between p-3 rounded bg-base-200">
                            <div class="flex items-center space-x-3">
                                <x-icon name="o-arrow-path" class="w-5 h-5 text-warning" />
                                <div>
                                    <div class="font-medium">Changement de statut</div>
                                    <div class="text-sm text-gray-500">Statut: {{ $accountStatusOptions[$account->account_status] ?? $account->account_status }}</div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($account->status_last_date)->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        @endif

                        @if($account->close_date)
                        <div class="flex items-center justify-between p-3 rounded bg-error/10">
                            <div class="flex items-center space-x-3">
                                <x-icon name="o-x-circle" class="w-5 h-5 text-error" />
                                <div>
                                    <div class="font-medium">Fermeture du compte</div>
                                    <div class="text-sm text-gray-500">Compte fermé définitivement</div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($account->close_date)->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        @endif

                        <div class="flex items-center justify-between p-3 rounded bg-base-200">
                            <div class="flex items-center space-x-3">
                                <x-icon name="o-server" class="w-5 h-5 text-gray-500" />
                                <div>
                                    <div class="font-medium">Dernière synchronisation</div>
                                    <div class="text-sm text-gray-500">Mise à jour des données</div>
                                </div>
                            </div>
                            <span class="text-sm text-gray-500">
                                {{ $account->load_data_ts ? \Carbon\Carbon::parse($account->load_data_ts)->format('d/m/Y H:i') : '-' }}
                            </span>
                        </div>
                    </div>
                </x-card>

            @else
                {{-- ANALYTICS TAB --}}
                <div class="space-y-6">
                    <x-card title="Analyse des Performances" icon="o-chart-bar">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Score de Santé</div>
                                <div class="stat-value {{ $accountHealth['class'] }}">{{ $accountHealth['score'] }}%</div>
                                <div class="stat-desc">{{ $accountHealth['level'] }}</div>
                            </div>

                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Activité (30j)</div>
                                <div class="stat-value text-info">{{ $accountStats['last_30_days_transactions'] }}</div>
                                <div class="stat-desc">transactions</div>
                            </div>

                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Dernière Transaction</div>
                                <div class="stat-value text-primary">{{ $accountStats['days_since_last_transaction'] }}</div>
                                <div class="stat-desc">jours</div>
                            </div>

                            <div class="rounded-lg stat bg-base-200">
                                <div class="stat-title">Volatilité</div>
                                <div class="stat-value text-accent">
                                    {{ number_format((($accountStats['highest_balance'] - $accountStats['lowest_balance']) / max($accountStats['average_monthly_balance'], 1)) * 100, 1) }}%
                                </div>
                                <div class="stat-desc">écart de solde</div>
                            </div>
                        </div>
                    </x-card>

                    <x-card title="Tendances" icon="o-trending-up">
                        <div class="space-y-4">
                            <div class="alert alert-info">
                                <x-icon name="o-information-circle" class="w-5 h-5" />
                                <span>Les données d'analyse détaillée nécessitent l'intégration avec le système de transactions.</span>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <h4 class="mb-2 font-semibold">Évolution du Solde</h4>
                                    <div class="flex items-center justify-center h-32 rounded-lg bg-base-200">
                                        <span class="text-gray-500">Graphique à implémenter</span>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-2 font-semibold">Volume de Transactions</h4>
                                    <div class="flex items-center justify-center h-32 rounded-lg bg-base-200">
                                        <span class="text-gray-500">Graphique à implémenter</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>
            @endif
        </div>

        {{-- SIDEBAR --}}
        <div class="space-y-6">
            {{-- QUICK ACTIONS --}}
            <x-card title="Actions Rapides" icon="o-lightning-bolt">
                <div class="space-y-2">
                    @can('customer-accounts.edit')
                        <x-button label="Modifier Compte"
                                  icon="o-pencil"
                                  wire:click="showEditModal"
                                  class="w-full btn-primary" />
                    @endcan

                    <x-button label="Voir Transactions"
                              icon="o-eye"
                              class="w-full btn-outline" />

                    <x-button label="Historique Complet"
                              icon="o-clock"
                              class="w-full btn-outline" />

                    <div class="divider"></div>

                    @if($account->account_status === '03')
                        @can('customer-accounts.manage')
                            <x-button label="Suspendre"
                                      icon="o-pause"
                                      wire:click="showStatusModal"
                                      class="w-full btn-warning" />
                            <x-button label="Bloquer"
                                      icon="o-no-symbol"
                                      wire:click="blockAccount"
                                      class="w-full btn-error" />
                        @endcan
                    @elseif(in_array($account->account_status, ['04', '06']))
                        @can('customer-accounts.manage')
                            <x-button label="Réactiver"
                                      icon="o-check-circle"
                                      wire:click="unblockAccount"
                                      class="w-full btn-success" />
                        @endcan
                    @endif

                    @if($account->account_status !== '05')
                        @can('customer-accounts.manage')
                            <x-button label="Fermer Compte"
                                      icon="o-x-circle"
                                      wire:click="closeAccount"
                                      class="w-full btn-error" />
                        @endcan
                    @endif
                </div>
            </x-card>

            {{-- ACCOUNT SUMMARY --}}
            <x-card title="Résumé" icon="o-document-text">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm">Type</span>
                        <x-badge value="Type {{ $account->account_type_id }}" class="badge-outline" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Devise</span>
                        <x-badge value="{{ $account->currency ?? 'EUR' }}" class="badge-info" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Statut</span>
                        <x-badge value="{{ $accountStatusOptions[$account->account_status] ?? $account->account_status }}"
                                 class="{{ $statusClass }}" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm">Ouvert depuis</span>
                        <span class="text-sm">
                            {{ $account->open_date ? \Carbon\Carbon::parse($account->open_date)->diffForHumans() : '-' }}
                        </span>
                    </div>
                    @if($account->default_flag === 'Y')
                    <div class="flex justify-between">
                        <span class="text-sm">Compte principal</span>
                        <x-badge value="Oui" class="badge-primary badge-sm" />
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- TECHNICAL INFO --}}
            <x-card title="Informations Techniques" icon="o-cog">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">ID Identity:</span>
                        <span class="font-mono">{{ $account->identity_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Type Identity:</span>
                        <span class="font-mono">{{ $account->identity_type }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Value Type:</span>
                        <span class="font-mono">{{ $account->value_type ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Version:</span>
                        <span class="font-mono">{{ $account->version ?: '-' }}</span>
                    </div>
                    @if($account->account_rule_profile_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Profil Règles:</span>
                        <span class="font-mono">{{ $account->account_rule_profile_id }}</span>
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- NAVIGATION --}}
            <x-card title="Navigation" icon="o-map">
                <div class="space-y-2">
                    <x-button label="Liste des Comptes"
                              icon="o-list-bullet"
                              link="{{ route('customer.accounts.index') }}"
                              class="w-full btn-outline" />
                    @if($account->customer)
                        <x-button label="Profil Client"
                                  icon="o-user"
                                  link="/customers/{{ $account->customer->customer_id }}"
                                  class="w-full btn-outline" />
                        <x-button label="Autres Comptes Client"
                                  icon="o-credit-card"
                                  link="/customers/{{ $account->customer->customer_id }}/accounts"
                                  class="w-full btn-outline" />
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    {{-- EDIT MODAL --}}
    @if($showEditModal)
    <div class="modal modal-open">
        <div class="w-11/12 max-w-2xl modal-box">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold">Modifier le Compte {{ $account->account_no }}</h3>
                <button wire:click="closeEditModal" class="btn btn-sm btn-circle btn-ghost">✕</button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input label="Nom du compte"
                         wire:model="account.account_name"
                         placeholder="Nom descriptif" />

                <x-input label="Alias"
                         wire:model="account.alias"
                         placeholder="Alias du compte" />

                <x-select label="Devise"
                          :options="[
                              ['id' => 'EUR', 'name' => 'Euro (EUR)'],
                              ['id' => 'USD', 'name' => 'Dollar US (USD)'],
                              ['id' => 'GBP', 'name' => 'Livre Sterling (GBP)']
                          ]"
                          wire:model="account.currency" />

                <div class="flex items-center gap-4 pt-6">
                    <x-checkbox label="Compte par défaut"
                               wire:model="account.default_flag" />
                    <x-checkbox label="Digest activé"
                               wire:model="account.digest_flag" />
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="closeEditModal" class="btn btn-outline">Annuler</button>
                <button class="btn btn-primary">Sauvegarder</button>
            </div>
        </div>
    </div>
    @endif

    {{-- STATUS CHANGE MODAL --}}
    @if($showStatusModal)
    <div class="modal modal-open">
        <div class="modal-box">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold">Changer le Statut du Compte</h3>
                <button wire:click="closeStatusModal" class="btn btn-sm btn-circle btn-ghost">✕</button>
            </div>

            <div class="space-y-4">
                <x-select label="Nouveau Statut"
                          :options="collect($accountStatusOptions)->map(fn($label, $value) => ['id' => $value, 'name' => $label])->values()"
                          wire:model="newStatus"
                          placeholder="Sélectionner un statut" />

                <x-textarea label="Raison du changement"
                           wire:model="statusReason"
                           placeholder="Expliquez la raison de ce changement..."
                           rows="3" />

                @if($newStatus === '05')
                <div class="alert alert-warning">
                    <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                    <span>Attention: La fermeture d'un compte est irréversible.</span>
                </div>
                @endif
            </div>

            <div class="modal-action">
                <button wire:click="closeStatusModal" class="btn btn-outline">Annuler</button>
                <button wire:click="updateAccountStatus"
                        class="btn {{ $newStatus === '05' ? 'btn-error' : 'btn-primary' }}">
                    {{ $newStatus === '05' ? 'Fermer le Compte' : 'Changer le Statut' }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
