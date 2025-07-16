<?php

use App\Models\Customer;
use Livewire\Volt\Component;

new class extends Component {
    public Customer $customer;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function with(): array
    {
        return [
            'customer' => $this->customer,
            'accounts' => $this->customer->accounts,
            'kyc' => $this->customer->kyc,
            'totalBalance' => $this->customer->total_balance,
            'segment' => $this->customer->segment,
            'hasKyc' => $this->customer->has_kyc
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Détails Client" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-badge value="{{ $customer->status }}" class="badge-{{ $customer->status === 'ACTIVE' ? 'success' : 'neutral' }}" />
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="KYC" icon="o-identification" link="/customers/{{ $customer->customer_id }}/kyc" class="btn-outline" />
            <x-button label="Transactions" icon="o-credit-card" link="/customers/{{ $customer->customer_id }}/transactions" class="btn-outline" />
            <x-button label="Modifier" icon="o-pencil" link="/customers/{{ $customer->customer_id }}/edit" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- INFORMATIONS PRINCIPALES --}}
        <div class="lg:col-span-2">
            <x-card title="Informations Client" icon="o-user">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="ID Client" value="{{ $customer->customer_id }}" readonly />
                    <x-input label="Nom utilisateur" value="{{ $customer->user_name }}" readonly />
                    <x-input label="Nom public" value="{{ $customer->public_name }}" readonly />
                    <x-input label="Type de client" value="{{ $customer->customer_type }}" readonly />
                    <x-input label="Niveau de confiance" value="{{ $customer->trust_level }}" readonly />
                    <x-input label="Person ID" value="{{ $customer->person_id }}" readonly />
                    <x-input label="SP ID" value="{{ $customer->sp_id }}" readonly />
                    <x-input label="Statut" value="{{ $customer->status }}" readonly />
                </div>

                @if($customer->create_time)
                <div class="pt-4 mt-4 border-t">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input label="Date de création" value="{{ \Carbon\Carbon::parse($customer->create_time)->format('d/m/Y H:i') }}" readonly />
                        @if($customer->modify_time)
                        <x-input label="Dernière modification" value="{{ \Carbon\Carbon::parse($customer->modify_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                    </div>
                </div>
                @endif
            </x-card>

            {{-- COMPTES --}}
            @if($accounts && $accounts->count() > 0)
            <x-card title="Comptes" icon="o-banknotes" class="mt-6">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>N° Compte</th>
                                <th>Alias</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Solde</th>
                                <th>Devise</th>
                                <th>Date ouverture</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                            <tr>
                                <td class="font-mono">{{ $account->account_no }}</td>
                                <td>{{ $account->alias ?? '-' }}</td>
                                <td>{{ $account->account_type_id ?? '-' }}</td>
                                <td>
                                    <x-badge value="{{ $account->account_status }}"
                                             class="badge-{{ $account->account_status === '03' ? 'success' : 'neutral' }}" />
                                </td>
                                <td class="font-mono">{{ number_format($account->balance ?? 0, 2) }}</td>
                                <td>{{ $account->currency ?? 'EUR' }}</td>
                                <td>{{ $account->open_date ? \Carbon\Carbon::parse($account->open_date)->format('d/m/Y') : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif
        </div>

        {{-- SIDEBAR --}}
        <div class="space-y-6">
            {{-- RÉSUMÉ --}}
            <x-card title="Résumé" icon="o-chart-bar">
                <div class="space-y-4">
                    <div class="stat">
                        <div class="stat-title">Solde Total</div>
                        <div class="stat-value text-primary">{{ number_format($totalBalance, 2) }} DJF</div>
                        <div class="stat-desc">{{ $segment }}</div>
                    </div>

                    <div class="my-2 divider"></div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">KYC Complété</span>
                        @if($hasKyc)
                            <x-badge value="Oui" class="badge-success" />
                        @else
                            <x-badge value="Non" class="badge-warning" />
                        @endif
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Comptes Actifs</span>
                        <x-badge value="{{ $customer->activeAccounts->count() }}" class="badge-info" />
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Total Comptes</span>
                        <x-badge value="{{ $accounts->count() }}" class="badge-neutral" />
                    </div>
                </div>
            </x-card>

            {{-- ACTIONS RAPIDES --}}
            <x-card title="Actions Rapides" icon="o-lightning-bolt">
                <div class="space-y-2">
                    <x-button label="Voir KYC" icon="o-identification" link="/customers/{{ $customer->customer_id }}/kyc" class="w-full btn-outline" />
                    <x-button label="Transactions" icon="o-credit-card" link="/customers/{{ $customer->customer_id }}/transactions" class="w-full btn-outline" />
                    <x-button label="Modifier" icon="o-pencil" link="/customers/{{ $customer->customer_id }}/edit" class="w-full btn-primary" />
                </div>
            </x-card>

            {{-- INFORMATIONS SYSTÈME --}}
            @if($customer->create_oper_id || $customer->modify_oper_id)
            <x-card title="Informations Système" icon="o-cog">
                <div class="space-y-2 text-sm">
                    @if($customer->create_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé par:</span>
                        <span class="font-mono">{{ $customer->create_oper_id }}</span>
                    </div>
                    @endif
                    @if($customer->modify_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Modifié par:</span>
                        <span class="font-mono">{{ $customer->modify_oper_id }}</span>
                    </div>
                    @endif
                    @if($customer->channel_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Canal:</span>
                        <span class="font-mono">{{ $customer->channel_id }}</span>
                    </div>
                    @endif
                </div>
            </x-card>
            @endif
        </div>
    </div>
</div>
