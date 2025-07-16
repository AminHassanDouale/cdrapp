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
    public string $value_type = '';

    #[Url]
    public string $account_status = '';

    #[Url]
    public array $sortBy = ['column' => 'account_no', 'direction' => 'asc'];

    public bool $showFilters = false;

    public function mount(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function clearFilters()
    {
        $this->reset([
            'account_no',
            'alias',
            'account_type_id',
            'currency',
            'value_type',
            'account_status'
        ]);

        $this->resetPage();
    }

    public function filterCount(): int
    {
        return collect([
            $this->account_no,
            $this->alias,
            $this->account_type_id,
            $this->currency,
            $this->value_type,
            $this->account_status
        ])->filter(fn($value) => !empty($value))->count();
    }

    public function accounts(): LengthAwarePaginator
    {
        $query = OrganizationAccount::query()
            ->where('identity_id', $this->organization->biz_org_id);

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

        if (!empty($this->value_type)) {
            $query->where('value_type', $this->value_type);
        }

        if (!empty($this->account_status)) {
            $query->where('account_status', $this->account_status);
        }

        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate(10);
    }

    public function headers(): array
    {
        return [
            ['key' => 'account_no', 'label' => 'N° Compte'],
            ['key' => 'alias', 'label' => 'Alias', 'class' => 'hidden lg:table-cell'],
            ['key' => 'account_type_id', 'label' => 'Type', 'class' => 'hidden lg:table-cell'],
            ['key' => 'value_type', 'label' => 'Type de valeur', 'class' => 'hidden xl:table-cell'],
            ['key' => 'balance_formatted', 'label' => 'Solde', 'sortBy' => 'balance'],
            ['key' => 'currency', 'label' => 'Devise', 'class' => 'hidden lg:table-cell'],
            ['key' => 'account_status_badge', 'label' => 'Statut', 'sortBy' => 'account_status'],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false]
        ];
    }

    public function accountStatusOptions(): array
    {
        return [
            ['id' => '01', 'name' => 'Inactif'],
            ['id' => '03', 'name' => 'Actif'],
            ['id' => '05', 'name' => 'Suspendu'],
            ['id' => '07', 'name' => 'Bloqué'],
            ['id' => '09', 'name' => 'Fermé']
        ];
    }

    public function currencyOptions(): array
    {
        return [
            ['id' => 'EUR', 'name' => 'Euro (EUR)'],
            ['id' => 'USD', 'name' => 'Dollar US (USD)'],
            ['id' => 'GBP', 'name' => 'Livre Sterling (GBP)'],
            ['id' => 'CHF', 'name' => 'Franc Suisse (CHF)'],
            ['id' => 'JPY', 'name' => 'Yen Japonais (JPY)']
        ];
    }

    public function with(): array
    {
        return [
            'organization' => $this->organization,
            'headers' => $this->headers(),
            'accounts' => $this->accounts(),
            'accountStatusOptions' => $this->accountStatusOptions(),
            'currencyOptions' => $this->currencyOptions(),
            'filterCount' => $this->filterCount(),
            'totalBalance' => $this->organization->total_balance,
            'activeAccountsCount' => $this->organization->accounts()->active()->count(),
            'totalAccountsCount' => $this->organization->accounts()->count()
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Comptes - {{ $organization->public_name ?? $organization->biz_org_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="{{ $totalAccountsCount }} comptes" class="badge-neutral" />
                <x-badge value="{{ number_format($totalBalance, 2) }} €" class="badge-primary" />
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

            <x-button label="Retour" icon="o-arrow-left" link="/organizations/{{ $organization->biz_org_id }}" class="btn-outline" />
            <x-button label="Nouveau compte" icon="o-plus" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    {{-- STATISTIQUES RAPIDES --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <x-card>
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-icon name="o-banknotes" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Comptes</div>
                <div class="stat-value text-primary">{{ $totalAccountsCount }}</div>
                <div class="stat-desc">Tous statuts</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-success">
                    <x-icon name="o-check-circle" class="w-8 h-8" />
                </div>
                <div class="stat-title">Comptes Actifs</div>
                <div class="stat-value text-success">{{ $activeAccountsCount }}</div>
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
                <div class="stat-desc">Euros</div>
            </div>
        </x-card>

        <x-card>
            <div class="stat">
                <div class="stat-figure text-warning">
                    <x-icon name="o-scale" class="w-8 h-8" />
                </div>
                <div class="stat-title">Solde Moyen</div>
                <div class="stat-value text-warning">{{ $totalAccountsCount > 0 ? number_format($totalBalance / $totalAccountsCount, 2) : '0.00' }}</div>
                <div class="stat-desc">Par compte</div>
            </div>
        </x-card>
    </div>

    {{-- FILTERS SECTION --}}
    @if($showFilters)
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-input label="N° Compte" wire:model.live.debounce="account_no" icon="o-hashtag" />

            <x-input label="Alias" wire:model.live.debounce="alias" icon="o-tag" />

            <x-input label="Type de compte" wire:model.live.debounce="account_type_id" icon="o-rectangle-stack" />

            <x-input label="Type de valeur" wire:model.live.debounce="value_type" icon="o-currency-euro" />

            <x-select
                label="Devise"
                :options="$currencyOptions"
                wire:model.live="currency"
                icon="o-currency-euro"
                placeholder="Toutes les devises"
                placeholder-value="" />

            <x-select
                label="Statut"
                :options="$accountStatusOptions"
                wire:model.live="account_status"
                icon="o-flag"
                placeholder="Tous les statuts"
                placeholder-value="" />
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
        <x-table :headers="$headers" :rows="$accounts" :sort-by="$sortBy" with-pagination>
            @scope('cell_balance_formatted', $account)
            <span class="font-mono">{{ number_format($account->balance ?? 0, 2) }}</span>
            @endscope

            @scope('cell_account_status_badge', $account)
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
                    class="btn-ghost btn-sm"
                    tooltip="Voir détails" />

                <x-button
                    icon="o-arrow-right-left"
                    class="btn-ghost btn-sm"
                    tooltip="Transactions" />

                <x-button
                    icon="o-pencil"
                    class="btn-ghost btn-sm"
                    tooltip="Modifier" />

                @if($account->account_status === '03')
                <x-button
                    icon="o-pause"
                    class="btn-ghost btn-sm text-warning"
                    tooltip="Suspendre" />
                @endif
            </div>
            @endscope
        </x-table>
    </x-card>
</div>
