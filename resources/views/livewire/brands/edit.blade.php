<?php

use App\Models\Brand;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

/**
 * It is adviced to avoid this `inline modal` approach, because it requires extra network requests.
 * It is implemented here just for demo purposes, use it as last resort.
 */
new class extends Component {
    use Toast;

    #[Modelable]
    public ?Brand $brand = null;

    public bool $show = false;

    #[Rule('required')]
    public string $name = '';

    public function boot(): void
    {
        if (! $this->brand) {
            $this->show = false;

            return;
        }

        $this->fill($this->brand);
        $this->show = true;
    }

    public function save(): void
    {
        if ($this->brand->id <= 3) {
            $this->addError('name', 'You can not modify this demo record, create a new one.');

            return;
        }

        $this->brand->update($this->validate());

        $this->reset();
        $this->dispatch('brand-saved');
        $this->success('Brand updated.');
    }
}; ?>

<div>
    <x-modal wire:model="show" title="Update Brand" persistent>
        <x-hr class="mb-5" />
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />

            <x-slot:actions>
                <x-button label="Cancel" @click="$dispatch('brand-cancel')" />
                <x-button label="Update" icon="o-paper-airplane" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
