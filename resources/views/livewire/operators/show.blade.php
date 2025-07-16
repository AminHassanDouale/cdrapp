<?php

use App\Models\Operator;
use Livewire\Volt\Component;

new class extends Component {
    public Operator $operator;

    public function mount(Operator $operator)
    {
        $this->operator = $operator;
    }

    public function with(): array
    {
        return [
            'operator' => $this->operator,
            'kyc' => $this->operator->kyc,
            'hasKyc' => $this->operator->has_kyc
        ];
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header title="Détails Opérateur" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-badge value="{{ $operator->status }}" class="badge-{{ $operator->status === '03' ? 'success' : 'neutral' }}" />
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="KYC" icon="o-identification" link="/operators/{{ $operator->operator_id }}/kyc" class="btn-outline" />
            <x-button label="Activité" icon="o-eye" link="/operators/{{ $operator->operator_id }}/activity" class="btn-outline" />
            <x-button label="Modifier" icon="o-pencil" link="/operators/{{ $operator->operator_id }}/edit" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- INFORMATIONS PRINCIPALES --}}
        <div class="lg:col-span-2">
            <x-card title="Informations Opérateur" icon="o-user">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="ID Opérateur" value="{{ $operator->operator_id }}" readonly />
                    <x-input label="Code opérateur" value="{{ $operator->operator_code }}" readonly />
                    <x-input label="Nom utilisateur" value="{{ $operator->user_name }}" readonly />
                    <x-input label="Nom public" value="{{ $operator->public_name }}" readonly />
                    <x-input label="Type propriétaire" value="{{ $operator->identity_type_name }}" readonly />
                    <x-input label="ID Propriétaire" value="{{ $operator->owned_identity_id }}" readonly />
                    <x-input label="SP ID" value="{{ $operator->sp_id }}" readonly />
                    <x-input label="Till ID par défaut" value="{{ $operator->default_till_id }}" readonly />
                    <x-input label="Statut" value="{{ $operator->status }}" readonly />
                    @if($operator->is_admin)
                    <x-input label="Administrateur" value="Oui" readonly />
                    @endif
                </div>

                @if($operator->active_time || $operator->create_time)
                <div class="pt-4 mt-4 border-t">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @if($operator->active_time)
                        <x-input label="Date d'activation" value="{{ $operator->active_time_formatted }}" readonly />
                        @endif
                        @if($operator->create_time)
                        <x-input label="Date de création" value="{{ $operator->create_time_formatted }}" readonly />
                        @endif
                        @if($operator->modify_time)
                        <x-input label="Dernière modification" value="{{ \Carbon\Carbon::parse($operator->modify_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                        @if($operator->status_change_time)
                        <x-input label="Changement de statut" value="{{ \Carbon\Carbon::parse($operator->status_change_time)->format('d/m/Y H:i') }}" readonly />
                        @endif
                    </div>
                </div>
                @endif
            </x-card>

            {{-- INFORMATIONS TECHNIQUES --}}
            @if($operator->rule_profile_id || $operator->language_code || $operator->access_channel)
            <x-card title="Informations Techniques" icon="o-cog" class="mt-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($operator->rule_profile_id)
                    <x-input label="Profil de règles" value="{{ $operator->rule_profile_id }}" readonly />
                    @endif
                    @if($operator->language_code)
                    <x-input label="Code langue" value="{{ $operator->language_code }}" readonly />
                    @endif
                    @if($operator->access_channel)
                    <x-input label="Canal d'accès" value="{{ $operator->access_channel }}" readonly />
                    @endif
                    @if($operator->channel_id)
                    <x-input label="ID Canal" value="{{ $operator->channel_id }}" readonly />
                    @endif
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
                        <div class="stat-title">Statut Opérateur</div>
                        <div class="stat-value text-primary">{{ $operator->status }}</div>
                        <div class="stat-desc">{{ $operator->identity_type_name }}</div>
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
                        <span class="text-sm">Type de propriétaire</span>
                        <x-badge value="{{ $operator->identity_type_name }}" class="badge-info" />
                    </div>

                    @if($operator->is_admin)
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Administrateur</span>
                        <x-badge value="Oui" class="badge-primary" />
                    </div>
                    @endif

                    @if($operator->default_till_id)
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Till par défaut</span>
                        <x-badge value="{{ $operator->default_till_id }}" class="badge-neutral" />
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- ACTIONS RAPIDES --}}
            <x-card title="Actions Rapides" icon="o-lightning-bolt">
                <div class="space-y-2">
                    <x-button label="Voir KYC" icon="o-identification" link="/operators/{{ $operator->operator_id }}/kyc" class="w-full btn-outline" />
                    <x-button label="Activité" icon="o-eye" link="/operators/{{ $operator->operator_id }}/activity" class="w-full btn-outline" />
                    <x-button label="Modifier" icon="o-pencil" link="/operators/{{ $operator->operator_id }}/edit" class="w-full btn-primary" />
                </div>
            </x-card>

            {{-- INFORMATIONS SYSTÈME --}}
            @if($operator->create_oper_id || $operator->modify_oper_id)
            <x-card title="Informations Système" icon="o-cog">
                <div class="space-y-2 text-sm">
                    @if($operator->create_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé par:</span>
                        <span class="font-mono">{{ $operator->create_oper_id }}</span>
                    </div>
                    @endif
                    @if($operator->modify_oper_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Modifié par:</span>
                        <span class="font-mono">{{ $operator->modify_oper_id }}</span>
                    </div>
                    @endif
                    @if($operator->person_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Person ID:</span>
                        <span class="font-mono">{{ $operator->person_id }}</span>
                    </div>
                    @endif
                </div>
            </x-card>
            @endif

            {{-- NAVIGATION --}}
            <x-card title="Navigation" icon="o-map">
                <div class="space-y-2">
                    <x-button label="Retour à la liste" icon="o-list-bullet" link="/operators" class="w-full btn-ghost" />
                </div>
            </x-card>
        </div>
    </div>
</div>
