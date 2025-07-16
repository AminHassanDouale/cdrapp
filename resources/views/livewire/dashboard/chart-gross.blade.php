<?php

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
    #[Reactive]
    public string $period = '-30 days';

    public array $chartGross = [
        'type' => 'line',
        'options' => [
            'backgroundColor' => '#dfd7f7',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'display' => false
                ],
                'y' => [
                    'display' => false
                ]
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ]
            ],
        ],
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => [],
                    'tension' => '0.1',
                    'fill' => true,
                ],
            ]
        ]
    ];

    #[Computed('refreshChartGross')]
    public function getRefreshChartGrossProperty(): array
    {
        $tasks = Task::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as day, COUNT(*) as task_count")
            ->groupBy('day')
            ->where('created_at', '>=', Carbon::parse($this->period)->startOfDay())
            ->get();

        $labels = $tasks->pluck('day');
        $data = $tasks->pluck('task_count');

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => $data,
                    'tension' => '0.1',
                    'fill' => true,
                ],
            ]
        ];
    }

    public function with(): array
    {
        return [
            'chartGross' => $this->refreshChartGross,
        ];
    }
};
 ?>

<div>
    <x-card title="Gross" separator shadow>
        <x-chart wire:model="chartGross" class="h-44" />
    </x-card>
</div>
