<?php

use App\Actions\DeleteCustomerAction;
use App\Models\Country;
use App\Models\User;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required')]
    public string $name = '';

    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required')]
    public ?int $country_id = null;

    #[Rule('nullable|image|max:1024')]
    public $avatar_file;

    public function save(): void
    {
        // Validate
        $data = $this->validate();

        // Create
        $data['password'] = Hash::make('demo');
        $data['avatar'] = '/images/empty-user.jpg';
        $user = User::create($data);

        if ($this->avatar_file) {
            $url = $this->avatar_file->store('users', 'public');
            $user->update(['avatar' => "/storage/$url"]);
        }

        $this->success('Customer created with success.', redirectTo: '/users');
    }

    public function with(): array
    {
        return [
            'countries' => Country::all(),
        ];
    }
}; ?>

<div>
    <x-header title="New Customer" separator progress-indicator />

    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-form wire:submit="save">
                <x-file label="Avatar" wire:model="avatar_file" accept="image/png, image/jpeg" hint="Click to change | Max 1MB" crop-after-change>
                    <img src="{{ $user->avatar ?? '/images/empty-user.jpg' }}" class="h-40 rounded-lg mb-3" />
                </x-file>
                <x-input label="Name" wire:model="name" />
                <x-input label="Email" wire:model="email" />
                <x-select label="Country" wire:model="country_id" :options="$countries" placeholder="---" />

                <x-slot:actions>
                    <x-button label="Cancel" link="/users" />
                    <x-button label="Create" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
                </x-slot:actions>
            </x-form>
        </div>
        <div>
            <img src="/images/edit-form.png" width="300" class="mx-auto" />
        </div>
    </div>
</div>
