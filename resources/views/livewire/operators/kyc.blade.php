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
    <x-header title="KYC - {{ $operator->public_name ?? $operator->user_name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            @if($hasKyc)
                <x-badge value="KYC Complété" class="badge-success" />
            @else
                <x-badge value="KYC Manquant" class="badge-warning" />
            @endif
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Retour" icon="o-arrow-left" link="/operators/{{ $operator->operator_id }}" class="btn-outline" />
            <x-button label="Activité" icon="o-eye" link="/operators/{{ $operator->operator_id }}/activity" class="btn-outline" />
            @if($hasKyc)
            <x-button label="Modifier KYC" icon="o-pencil" class="btn-primary" />
            @else
            <x-button label="Créer KYC" icon="o-plus" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- INFORMATIONS OPÉRATEUR --}}
        <div class="lg:col-span-2">
            {{-- INFORMATIONS DE BASE --}}
            <x-card title="Informations Opérateur" icon="o-user">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="ID Opérateur" value="{{ $operator->operator_id }}" readonly />
                    <x-input label="Code opérateur" value="{{ $operator->operator_code }}" readonly />
                    <x-input label="Nom utilisateur" value="{{ $operator->user_name }}" readonly />
                    <x-input label="Nom public" value="{{ $operator->public_name }}" readonly />
                    <x-input label="Type propriétaire" value="{{ $operator->identity_type_name }}" readonly />
                    <x-input label="ID Propriétaire" value="{{ $operator->owned_identity_id }}" readonly />
                    <x-input label="Statut" value="{{ $operator->status }}" readonly />
                    @if($operator->is_admin)
                    <x-input label="Administrateur" value="Oui" readonly />
                    @endif
                </div>
            </x-card>

            {{-- DONNÉES KYC --}}
            @if($hasKyc && $kyc)
            <x-card title="Données KYC" icon="o-identification" class="mt-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {{-- Display only non-empty KYC fields --}}
                    @for($i = 1; $i <= 100; $i++)
                        @php $fieldName = "field_{$i}"; @endphp
                        @if($kyc->$fieldName && !empty($kyc->$fieldName))
                        <x-input
                            label="Champ {{ $i }}"
                            value="{{ $kyc->$fieldName }}"
                            readonly />
                        @endif
                    @endfor
                </div>

                @if($kyc->load_data_ts)
                <div class="pt-4 mt-4 border-t">
                    <x-input label="Dernière mise à jour" value="{{ $kyc->load_data_ts }}" readonly />
                </div>
                @endif
            </x-card>
            @else
            {{-- PAS DE KYC --}}
            <x-card title="Aucune donnée KYC" icon="o-exclamation-triangle" class="mt-6">
                <div class="py-8 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-warning/10">
                        <x-icon name="o-exclamation-triangle" class="w-8 h-8 text-warning" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Aucune information KYC</h3>
                    <p class="mb-4 text-gray-600">Cet opérateur n'a pas encore complété son processus KYC.</p>
                    <x-button label="Créer KYC" icon="o-plus" class="btn-primary" />
                </div>
            </x-card>
            @endif

            {{-- HISTORIQUE DES VÉRIFICATIONS --}}
            <x-card title="Historique des Vérifications" icon="o-clock" class="mt-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded bg-base-200">
                        <div class="flex items-center space-x-3">
                            <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                            <div>
                                <div class="font-medium">Statut opérateur: {{ $operator->status }}</div>
                                <div class="text-sm text-gray-500">Statut actuel</div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">
                            {{ $operator->active_time ? \Carbon\Carbon::parse($operator->active_time)->format('d/m/Y') : '-' }}
                        </span>
                    </div>

                    @if($hasKyc)
                    <div class="flex items-center justify-between p-3 rounded bg-success/10">
                        <div class="flex items-center space-x-3">
                            <x-icon name="o-shield-check" class="w-5 h-5 text-success" />
                            <div>
                                <div class="font-medium">KYC Complété</div>
                                <div class="text-sm text-gray-500">Vérification d'identité terminée</div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">
                            {{ $kyc->load_data_ts ? \Carbon\Carbon::parse($kyc->load_data_ts)->format('d/m/Y') : '-' }}
                        </span>
                    </div>
                    @endif

                    @if($operator->status_change_time)
                    <div class="flex items-center justify-between p-3 rounded bg-base-200">
                        <div class="flex items-center space-x-3">
                            <x-icon name="o-arrow-path" class="w-5 h-5 text-info" />
                            <div>
                                <div class="font-medium">Changement de statut</div>
                                <div class="text-sm text-gray-500">Statut: {{ $operator->status }}</div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($operator->status_change_time)->format('d/m/Y') }}
                        </span>
                    </div>
                    @endif

                    @if($operator->create_time)
                    <div class="flex items-center justify-between p-3 rounded bg-info/10">
                        <div class="flex items-center space-x-3">
                            <x-icon name="o-plus-circle" class="w-5 h-5 text-info" />
                            <div>
                                <div class="font-medium">Opérateur créé</div>
                                <div class="text-sm text-gray-500">Première création</div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($operator->create_time)->format('d/m/Y') }}
                        </span>
                    </div>
                    @endif
                </div>
            </x-card>
        </div>

        {{-- SIDEBAR --}}
        <div class="space-y-6">
            {{-- STATUT KYC --}}
            <x-card title="Statut KYC" icon="o-shield-check">
                <div class="space-y-4">
                    <div class="text-center">
                        @if($hasKyc)
                            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-3 rounded-full bg-success/10">
                                <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                            </div>
                            <h3 class="font-semibold text-success">KYC Complété</h3>
                            <p class="text-sm text-gray-600">Vérification terminée</p>
                        @else
                            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-3 rounded-full bg-warning/10">
                                <x-icon name="o-exclamation-triangle" class="w-8 h-8 text-warning" />
                            </div>
                            <h3 class="font-semibold text-warning">KYC Manquant</h3>
                            <p class="text-sm text-gray-600">Vérification requise</p>
                        @endif
                    </div>

                    <div class="my-2 divider"></div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Statut opérateur</span>
                            <x-badge value="{{ $operator->status }}"
                                     class="badge-{{ $operator->status === '03' ? 'success' : 'neutral' }}" />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Type propriétaire</span>
                            <x-badge value="{{ $operator->identity_type_name }}" class="badge-neutral" />
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
                            <x-badge value="{{ $operator->default_till_id }}" class="badge-info" />
                        </div>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- ACTIONS --}}
            <x-card title="Actions" icon="o-cog">
                <div class="space-y-2">
                    @if($hasKyc)
                    <x-button label="Modifier KYC" icon="o-pencil" class="w-full btn-primary" />
                    <x-button label="Télécharger KYC" icon="o-arrow-down-tray" class="w-full btn-outline" />
                    @else
                    <x-button label="Créer KYC" icon="o-plus" class="w-full btn-primary" />
                    <x-button label="Importer KYC" icon="o-arrow-up-tray" class="w-full btn-outline" />
                    @endif
                    <x-button label="Historique" icon="o-clock" class="w-full btn-outline" />
                </div>
            </x-card>

            {{-- NAVIGATION --}}
            <x-card title="Navigation" icon="o-map">
                <div class="space-y-2">
                    <x-button label="Détails Opérateur" icon="o-user" link="/operators/{{ $operator->operator_id }}" class="w-full btn-outline" />
                    <x-button label="Activité" icon="o-eye" link="/operators/{{ $operator->operator_id }}/activity" class="w-full btn-outline" />
                    <x-button label="Retour à la liste" icon="o-list-bullet" link="/operators" class="w-full btn-ghost" />
                </div>
            </x-card>
        </div>
    </div>
</div>
