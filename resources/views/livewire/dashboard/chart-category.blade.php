<?php

use App\Models\Product;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component {
    #[Reactive]
    public string $period = '-30 days';

    public array $chartCategory = [
        'type' => 'doughnut',
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'left',
                    'labels' => [
                        'usePointStyle' => true
                    ]
                ]
            ],
        ],
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Sold',
                    'data' => [],
                ]
            ]
        ]
    ];

    #[Computed]
    public function refreshChartCategory(): void
    {
        $sales = Product::query()
            ->with('category')
            ->selectRaw("count(category_id) as total, category_id")
            ->whereRelation('sales.order', 'created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->groupBy('category_id')
            ->get();

        Arr::set($this->chartCategory, 'data.labels', $sales->pluck('category.name'));
        Arr::set($this->chartCategory, 'data.datasets.0.data', $sales->pluck('total'));
    }

    public function with(): array
    {
        $this->refreshChartCategory();

        return [];
    }
}; ?>

<div>
    <x-card title="Category" separator shadow>
        <x-chart wire:model="chartCategory" class="h-44" />
    </x-card>
</div>
