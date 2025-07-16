<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {
    public Order $order;

    public bool $change = false;

    public ?int $user_id = null;

    public Collection $users;

    public function mount(): void
    {
        $this->order->load(['user.country']);

        $this->search();
    }

    public function updateUser(?int $user_id): void
    {
        // Handle clear button from `x-choices`
        if (! $user_id) {
            $this->change = false;

            return;
        }

        $this->order->update(['user_id' => $user_id]);
        $this->change = false;
        $this->user_id = null;
    }

    public function search(string $value = ''): void
    {
        $selectedOption = User::where('id', $this->user_id)->get();

        $this->users = User::query()
            ->where('name', 'like', "%$value%")
            ->take(5)
            ->orderBy('name')
            ->get()
            ->merge($selectedOption);
    }
}; ?>

<div>
    <x-card title="Customer" progress-indicator="updateUser" separator shadow>
        <x-slot:menu>
            <x-button label="{{ $change ? 'Cancel' : 'Change' }}" wire:click="$toggle('change')" icon="{{ $change ? 'o-x-mark' : 'o-pencil' }}" class="btn-ghost btn-sm" />
        </x-slot:menu>

        @if(!$change)
            <x-avatar :image="$order->user?->avatar" class="!w-20">
                <x-slot:title class="pl-2">
                    {{ $order->user?->name }}
                </x-slot:title>
                <x-slot:subtitle class="flex flex-col gap-2 p-2 pl-2">
                    <x-icon name="o-envelope" :label="$order->user?->email" />
                    <x-icon name="o-map-pin" :label="$order->user?->country->name" />
                </x-slot:subtitle>
            </x-avatar>
        @else
            <x-choices
                wire:model.live="user_id"
                :options="$users"
                option-sub-label="email"
                hint="Search for customer name"
                icon="o-magnifying-glass"
                class="mt-5"
                @change-selection="$wire.updateUser($event.detail.value)"
                single
                searchable />
        @endif
    </x-card>
</div>
