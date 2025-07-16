<?php

use App\Models\Organization;
use Livewire\Volt\Component;

new class extends Component {
    public Organization $organization;

    public function mount(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function with(): array
    {
        return [
            'organization' => $this->organization,
            'accounts' => $this->organization->accounts,
            'kyc' => $this->organization->kyc,
            'totalBalance' => $this->organization->total_balance,
            'hasKyc' => $this->organization->has_kyc
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Détails Organisation" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-badge value="{{ $organization->status }}" class="badge-{{ $organization->status === '03' ? 'success' : 'neutral' }}" />
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="KYC" icon="o-identification" link="/organizations/{{ $organization->biz_org_id }}/kyc" class="btn-outline" />
            <x-button label="Comptes" icon="o-banknotes" link="/organizations/{{ $organization->biz_org_id }}/accounts" class="btn-outline" />
            <x-button label="Opérateurs" icon="o-users" link="/organizations/{{ $organization->biz_org_id }}/operators" class="btn-outline" />
            <x-button label="Modifier" icon="o-pencil" link="/organizations/{{ $organization->biz_org_id }}/edit" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- INFORMATIONS PRINCIPALES --}}
        <div class="lg:col-span-2">
            <x-card title="Informations Organisation" icon="o-building-office">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="ID Organisation" value="{{ $organization->biz_org_id }}" readonly />
                    <x-input label="Nom organisation" value="{{ $organization->biz_org_name }}" readonly />
                    <x-input label="Nom public" value="{{ $organization->public_name }}" readonly />
                    <x-input label="Type d'organisation" value="{{ $organization->organization_type }}" readonly />
                    <x-input label="Code organisation" value="{{ $organization->organization_code }}" readonly />
                    <x-input label="Code court" value="{{ $organization->short_code }}" readonly />
                    <x-input label="Niveau de confiance" value="{{ $organization->trust_level }}" readonly />
                    <x-input label="Person ID" value="{{ $organization->person_id }}" readonly />
                    <x-input label="SP ID" value="{{ $organization->sp_id }}" readonly />
                    <x-input label="Statut" value="{{ $organization->status }}" readonly />
                </div>

                @if($organization->create_time)
                <div class="pt-4 mt-4 border-t">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input label="Date de création" value="{{ \Carbon\Carbon::parse($organization->create_time)->format('d/m/Y H:i') }}" readonly />
                        @if($organization->modify_time)
                        <x-input label="Dernière modification" value="{{ \Carbon\Carbon::parse($organization->modify_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                        @if($organization->active_time)
                        <x-input label="Date d'activation" value="{{ \Carbon\Carbon::parse($organization->active_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                        @if($organization->status_change_time)
                        <x-input label="Changement de statut" value="{{ \Carbon\Carbon::parse($organization->status_change_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                    </div>
                </div>
                @endif
            </x-card>

            {{-- INFORMATIONS HIÉRARCHIQUES --}}
            @if($organization->hier_type || $organization->parent_id || $organization->is_top)
            <x-card title="Informations Hiérarchiques" icon="o-chart-bar-square" class="mt-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($organization->is_top)
                    <x-input label="Organisation de tête" value="{{ $organization->is_top ? 'Oui' : 'Non' }}" readonly />
                    @endif
                    @if($organization->hier_type)
                    <x-input label="Type hiérarchique" value="{{ $organization->hier_type }}" readonly />
                    @endif
                    @if($organization->hier_level)
                    <x-input label="Niveau hiérarchique" value="{{ $organization->hier_level }}" readonly />
                    @endif
                    @if($organization->parent_id)
                    <x-input label="Organisation parente" value="{{ $organization->parent_id }}" readonly />
                    @endif
                    @if($organization->top_biz_org)
                    <x-input label="Organisation de tête" value="{{ $organization->top_biz_org }}" readonly />
                    @endif
                    @if($organization->max_layer)
                    <x-input label="Couche maximale" value="{{ $organization->max_layer }}" readonly />
                    @endif
                </div>
            </x-card>
            @endif

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
                                <th>Solde</th>
                                <th>Devise</th>
                                <th>Type de valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                            <tr>
                                <td class="font-mono">{{ $account->account_no }}</td>
                                <td>{{ $account->alias ?? '-' }}</td>
                                <td>{{ $account->account_type_id ?? '-' }}</td>
                                <td class="font-mono">{{ number_format($account->balance ?? 0, 2) }}</td>
                                <td>{{ $account->currency ?? 'EUR' }}</td>
                                <td>{{ $account->value_type ?? '-' }}</td>
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
                        <div class="stat-value text-primary">{{ number_format($totalBalance, 2) }} €</div>
                        <div class="stat-desc">Tous comptes confondus</div>
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
                        <x-badge value="{{ $organization->accounts()->active()->count() }}" class="badge-info" />
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Total Comptes</span>
                        <x-badge value="{{ $accounts->count() }}" class="badge-neutral" />
                    </div>

                    @if($organization->is_top)
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Organisation de tête</span>
                        <x-badge value="Oui" class="badge-primary" />
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- ACTIONS RAPIDES --}}
            <x-card title="Actions Rapides" icon="o-lightning-bolt">
                <div class="space-y-2">
                    <x-button label="Voir KYC" icon="o-identification" link="/organizations/{{ $organization->biz_org_id }}/kyc" class="w-full btn-outline" />
                    <x-button label="Comptes" icon="o-banknotes" link="/organizations/{{ $organization->biz_org_id }}/accounts" class="w-full btn-outline" />
                    <x-button label="Opérateurs" icon="o-users" link="/organizations/{{ $organization->biz_org_id }}/operators" class="w-full btn-outline" />
                    <x-button label="Modifier" icon="o-pencil" link="/organizations/{{ $organization->biz_org_id }}/edit" class="w-full btn-primary" />
                </div>
            </x-card>

            {{-- INFORMATIONS SYSTÈME --}}
            @if($organization->create_oper_id || $organization->modify_oper_id)
            <x-card title="Informations Système" icon="o-cog">
                <div class="space-y-2 text-sm">
                    @if($organization->create_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé par:</span>
                        <span class="font-mono">{{ $organization->create_oper_id }}</span>
                    </div>
                    @endif
                    @if($organization->modify_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Modifié par:</span>
                        <span class="font-mono">{{ $organization->modify_oper_id }}</span>
                    </div>
                    @endif
                    @if($organization->channel_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Canal:</span>
                        <span class="font-mono">{{ $organization->channel_id }}</span>
                    </div>
                    @endif
                    @if($organization->region_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Région:</span>
                        <span class="font-mono">{{ $organization->region_id }}</span>
                    </div>
                    @endif
                </div>
            </x-card>
            @endif

            {{-- NAVIGATION --}}
            <x-card title="Navigation" icon="o-map">
                <div class="space-y-2">
                    <x-button label="Retour à la liste" icon="o-list-bullet" link="/organizations" class="w-full btn-ghost" />
                </div>
            </x-card>
        </div>
    </div>
</div>
