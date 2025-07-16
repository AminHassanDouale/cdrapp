<?php

use App\Exceptions\AppException;
use App\Models\Category;
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
    public ?Category $category = null;

    public bool $show = false;

    #[Rule('required')]
    public string $name = '';

    public function boot(): void
    {
        if (! $this->category) {
            $this->show = false;

            return;
        }

        $this->fill($this->category);
        $this->show = true;
    }

    public function save(): void
    {
        if ($this->category->id <= 3) {
            $this->addError('name', 'You can not modify this demo record, create a new one.');

            return;
        }

        $this->category->update($this->validate());

        $this->reset();
        $this->dispatch('category-saved');
        $this->success('Category updated.');
    }
}; ?>

<div>
    <x-modal wire:model="show" title="Update Category" persistent>
        <x-hr class="mb-5" />
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />

            <x-slot:actions>
                <x-button label="Cancel" @click="$dispatch('category-cancel')" />
                <x-button label="Update" icon="o-paper-airplane" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
