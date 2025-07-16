<?php

use App\Actions\DeleteProductAction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

new class extends Component {
    use Toast, WithFileUploads, WithMediaSync;

    #[Rule('required')]
    public string $brand_id;

    #[Rule('required')]
    public string $category_id;

    #[Rule('required')]
    public string $name;

    #[Rule('required|decimal:0,2|gt:0')]
    public ?float $price;

    #[Rule('required')]
    public string $description;

    #[Rule('required|integer')]
    public string $stock;

    #[Rule(['files.*' => 'image|max:1024'])]
    public array $files = [];

    #[Rule('nullable')]
    public ?Collection $library;

    #[Rule('nullable|image|max:1024')]
    public $cover_file;

    public function mount(): void
    {
        $this->library = new Collection();
    }

    #[On('brand-saved')]
    public function newBrand($id): void
    {
        $this->brand_id = $id;
    }

    #[On('category-saved')]
    public function newCategory($id): void
    {
        $this->category_id = $id;
    }

    public function brands(): Collection
    {
        return Brand::orderBy('name')->get();
    }

    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function save(): void
    {
        // Validate
        $data = $this->validate();

        // Create
        $data['cover'] = '/images/empty-product.png';
        $product = Product::create($data);

        // Upload cover
        if ($this->cover_file) {
            $url = $this->cover_file->store('products', 'public');
            $product->update(['cover' => "/storage/$url"]);
        }

        // Upload library images
        $this->syncMedia($product);

        $this->success('Product created with success.', redirectTo: '/products');
    }

    public function with(): array
    {
        return [
            'brands' => $this->brands(),
            'categories' => $this->categories()
        ];
    }
}; ?>

<div>
    <x-header title="Create product" separator />

    <x-form wire:submit="save">
        <div class="grid lg:grid-cols-2 gap-8">
            {{-- DETAILS --}}
            <x-card title="Details" separator>
                <div class="grid gap-3 lg:px-3" wire:key="details">
                    <x-input label="Name" wire:model="name" />
                    <x-editor label="Description" wire:model="description" :config="['height' => 200]" />

                    {{-- BRANDS --}}
                    <x-choices-offline label="Brand" wire:model="brand_id" :options="$brands" single searchable>
                        <x-slot:append>
                            <livewire:brands.create label="" class="rounded-l-none" />
                        </x-slot:append>
                    </x-choices-offline>

                    {{-- CATEGORIES --}}
                    <x-choices-offline label="Categories" wire:model="category_id" :options="$categories" single searchable>
                        <x-slot:append>
                            <livewire:categories.create label="" class="rounded-l-none" />
                        </x-slot:append>
                    </x-choices-offline>

                    {{-- PRICE & STOCK --}}
                    <x-input label="Price" wire:model="price" prefix="USD" money />
                    <x-input label="Stock" wire:model="stock" placeholder="Units" x-mask="999" />

                </div>
            </x-card>
            {{-- IMAGES --}}
            <div class="grid content-start gap-8">
                {{-- COVER IMAGE --}}
                <x-card title="Cover" separator>
                    <div class="flex">
                        <x-file wire:model="cover_file" accept="image/png, image/jpeg" hint="Click to change | Max 1MB" class="mx-auto">
                            <img src="{{  $product->cover ?? '/images/empty-product.png' }}" class="h-48 !rounded-lg my-3" />
                        </x-file>
                    </div>
                </x-card>

                {{-- MORE IMAGES --}}
                <x-card title="More images" separator>
                    <x-image-library wire:model="files" wire:library="library" :preview="$library" hint="Max 1MB" />
                </x-card>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Cancel" link="/products" />
            <x-button label="Save" icon="o-paper-airplane" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
