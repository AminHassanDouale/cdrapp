<?php

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?int $user_id = null;

    public Collection $users;

    public function mount(): void
    {
        $this->search();
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

    public function confirm(): void
    {
        $order = Order::create([
            'user_id' => $this->user_id,
            'status_id' => OrderStatus::DRAFT
        ]);

        $this->success('Order created', redirectTo: "/orders/{$order->id}/edit");
    }
}; ?>

<div>
    <x-header title="New Order" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Discard" link="/orders" icon="o-arrow-uturn-left" responsive />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-2 gap-8">
        {{-- CUSTOMER --}}
        <div class="content-start">
            <x-card title="Customer" separator shadow>
                <x-slot:menu>
                    @if($user_id)
                        <x-button label="Confirm" wire:click="confirm" icon="o-check" class="btn-sm btn-primary" />
                    @endif
                </x-slot:menu>

                <x-choices
                    wire:model.live="user_id"
                    :options="$users"
                    option-sub-label="email"
                    hint="Search for customer name"
                    icon="o-magnifying-glass"
                    single
                    searchable />
            </x-card>
        </div>

        {{-- IMAGE --}}
        <div>
            <img src="/images/new-order.png" class="mx-auto" width="300px" />
        </div>
    </div>

</div>
