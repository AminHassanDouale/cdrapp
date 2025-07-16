<?php
// resources/views/livewire/operators/performance.blade.php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use Toast, WithPagination;

    // Filter properties
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $selectedOperator = '';
    public string $departmentFilter = '';
    public string $shiftFilter = '';
    public string $metricType = 'overview';
    public string $comparisonPeriod = 'previous';
    public string $performanceView = 'individual'; // individual, team, department

    // Analysis properties
    public array $operators = [];
    public array $performanceData = [];
    public array $topPerformers = [];
    public array $performanceMetrics = [];
    public array $productivityTrends = [];
    public array $qualityMetrics = [];
    public array $customerSatisfaction = [];
    public array $performanceGoals = [];
    public array $alerts = [];

    // Modal properties
    public bool $showOperatorDetails = false;
    public bool $showPerformanceReport = false;
    public bool $showGoalSettings = false;
    public array $selectedOperatorData = [];

    // Goal setting properties
    public string $goalOperator = '';
    public string $goalMetric = '';
    public float $goalTarget = 0;
    public string $goalPeriod = 'monthly';
    public string $goalDescription = '';

    private const PERFORMANCE_METRICS = [
        'transaction_volume' => 'Transaction Volume',
        'success_rate' => 'Success Rate',
        'processing_time' => 'Avg Processing Time',
        'error_rate' => 'Error Rate',
        'customer_satisfaction' => 'Customer Satisfaction',
        'productivity_score' => 'Productivity Score',
        'compliance_score' => 'Compliance Score',
        'resolution_time' => 'Issue Resolution Time'
    ];

    private const SHIFT_TYPES = [
        'morning' => 'Morning (6AM-2PM)',
        'afternoon' => 'Afternoon (2PM-10PM)',
        'night' => 'Night (10PM-6AM)',
        'flexible' => 'Flexible Hours'
    ];

    private const DEPARTMENTS = [
        'customer_service' => 'Customer Service',
        'transaction_processing' => 'Transaction Processing',
        'compliance' => 'Compliance',
        'technical_support' => 'Technical Support',
        'fraud_prevention' => 'Fraud Prevention',
        'quality_assurance' => 'Quality Assurance'
    ];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadPerformanceData();
    }

    public function updatedDateFrom(): void
    {
        $this->loadPerformanceData();
    }

    public function updatedDateTo(): void
    {
        $this->loadPerformanceData();
    }

    public function updatedMetricType(): void
    {
        $this->loadPerformanceData();
    }

    public function updatedPerformanceView(): void
    {
        $this->loadPerformanceData();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'selectedOperator', 'departmentFilter', 'shiftFilter'
        ]);
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadPerformanceData();
        $this->success('Filters reset successfully');
    }

    public function loadPerformanceData(): void
    {
        try {
            $startDate = Carbon::parse($this->dateFrom);
            $endDate = Carbon::parse($this->dateTo);

            $this->operators = $this->getOperators();
            $this->performanceData = $this->getPerformanceData($startDate, $endDate);
            $this->topPerformers = $this->getTopPerformers($startDate, $endDate);
            $this->performanceMetrics = $this->getPerformanceMetrics($startDate, $endDate);
            $this->productivityTrends = $this->getProductivityTrends($startDate, $endDate);
            $this->qualityMetrics = $this->getQualityMetrics($startDate, $endDate);
            $this->customerSatisfaction = $this->getCustomerSatisfactionData($startDate, $endDate);
            $this->performanceGoals = $this->getPerformanceGoals();
            $this->alerts = $this->getPerformanceAlerts();

        } catch (\Exception $e) {
            logger()->error('Performance data loading error: ' . $e->getMessage());
            $this->error('Error loading performance data. Please try again.');
        }
    }

    private function getOperators(): array
    {
        // In a real application, you would have a User model with roles/departments
        // For now, we'll extract operators from transaction data and simulate user data
        $operators = collect();

        // Get operators from checker_id field (assuming this represents operators)
        $checkers = Transaction::whereNotNull('checker_id')
            ->where('checker_id', '!=', '')
            ->distinct()
            ->pluck('checker_id')
            ->take(50); // Limit for demo

        foreach ($checkers as $checkerId) {
            $operators->push([
                'id' => $checkerId,
                'name' => 'Operator ' . $checkerId,
                'email' => 'operator' . $checkerId . '@company.com',
                'department' => collect(array_keys(self::DEPARTMENTS))->random(),
                'shift' => collect(array_keys(self::SHIFT_TYPES))->random(),
                'hire_date' => now()->subDays(rand(30, 1095))->format('Y-m-d'),
                'status' => 'active'
            ]);
        }

        return $operators->toArray();
    }

    private function getPerformanceData($startDate, $endDate): array
    {
        $performanceData = [];

        foreach ($this->operators as $operator) {
            // Get transaction data for this operator
            $transactions = Transaction::where('checker_id', $operator['id'])
                ->whereBetween('trans_initate_time', [$startDate, $endDate])
                ->get();

            $totalTransactions = $transactions->count();
            $successfulTransactions = $transactions->where('trans_status', 'Completed')->count();
            $failedTransactions = $transactions->where('trans_status', 'Failed')->count();
            $totalVolume = $transactions->sum('actual_amount');
            $avgProcessingTime = $this->calculateAvgProcessingTime($transactions);

            $performanceData[] = [
                'operator_id' => $operator['id'],
                'operator_name' => $operator['name'],
                'department' => $operator['department'],
                'shift' => $operator['shift'],
                'total_transactions' => $totalTransactions,
                'successful_transactions' => $successfulTransactions,
                'failed_transactions' => $failedTransactions,
                'success_rate' => $totalTransactions > 0 ? ($successfulTransactions / $totalTransactions) * 100 : 0,
                'error_rate' => $totalTransactions > 0 ? ($failedTransactions / $totalTransactions) * 100 : 0,
                'total_volume' => $totalVolume,
                'avg_transaction_value' => $totalTransactions > 0 ? $totalVolume / $totalTransactions : 0,
                'avg_processing_time' => $avgProcessingTime,
                'productivity_score' => $this->calculateProductivityScore($totalTransactions, $successfulTransactions, $avgProcessingTime),
                'quality_score' => $this->calculateQualityScore($successfulTransactions, $failedTransactions),
                'compliance_score' => rand(85, 100), // Simulated compliance score
                'customer_satisfaction' => rand(3.5, 5.0), // Simulated rating out of 5
                'issues_resolved' => rand(0, 20),
                'avg_resolution_time' => rand(5, 45), // minutes
                'training_hours' => rand(0, 40),
                'certifications' => rand(2, 8)
            ];
        }

        // Sort by productivity score
        usort($performanceData, function($a, $b) {
            return $b['productivity_score'] <=> $a['productivity_score'];
        });

        return $performanceData;
    }

    private function calculateAvgProcessingTime($transactions): float
    {
        $processingTimes = [];

        foreach ($transactions as $transaction) {
            if ($transaction->trans_end_time && $transaction->trans_initate_time) {
                try {
                    $start = Carbon::parse($transaction->trans_initate_time);
                    $end = is_string($transaction->trans_end_time) ?
                           Carbon::parse($transaction->trans_end_time) :
                           $transaction->trans_end_time;

                    $processingTimes[] = $start->diffInSeconds($end);
                } catch (\Exception $e) {
                    // Skip invalid dates
                    continue;
                }
            }
        }

        return count($processingTimes) > 0 ? array_sum($processingTimes) / count($processingTimes) : 0;
    }

    private function calculateProductivityScore($total, $successful, $avgTime): float
    {
        if ($total === 0) return 0;

        // Productivity score based on volume, success rate, and speed
        $volumeScore = min(($total / 100) * 40, 40); // Max 40 points for volume
        $successScore = ($successful / $total) * 40; // Max 40 points for success rate
        $speedScore = $avgTime > 0 ? max(20 - ($avgTime / 60), 0) : 20; // Max 20 points for speed

        return $volumeScore + $successScore + $speedScore;
    }

    private function calculateQualityScore($successful, $failed): float
    {
        $total = $successful + $failed;
        if ($total === 0) return 0;

        return ($successful / $total) * 100;
    }

    private function getTopPerformers($startDate, $endDate): array
    {
        return array_slice($this->performanceData, 0, 10);
    }

    private function getPerformanceMetrics($startDate, $endDate): array
    {
        $allData = $this->performanceData;

        return [
            'total_operators' => count($allData),
            'active_operators' => count(array_filter($allData, fn($op) => $op['total_transactions'] > 0)),
            'avg_productivity_score' => count($allData) > 0 ? array_sum(array_column($allData, 'productivity_score')) / count($allData) : 0,
            'avg_success_rate' => count($allData) > 0 ? array_sum(array_column($allData, 'success_rate')) / count($allData) : 0,
            'total_transactions_processed' => array_sum(array_column($allData, 'total_transactions')),
            'total_volume_processed' => array_sum(array_column($allData, 'total_volume')),
            'avg_customer_satisfaction' => count($allData) > 0 ? array_sum(array_column($allData, 'customer_satisfaction')) / count($allData) : 0,
            'high_performers' => count(array_filter($allData, fn($op) => $op['productivity_score'] >= 80)),
            'needs_improvement' => count(array_filter($allData, fn($op) => $op['productivity_score'] < 60)),
        ];
    }

    private function getProductivityTrends($startDate, $endDate): array
    {
        // Generate simulated daily productivity trends
        $trends = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $trends[] = [
                'date' => $currentDate->format('Y-m-d'),
                'avg_productivity' => rand(65, 95),
                'total_transactions' => rand(100, 500),
                'active_operators' => rand(10, 25),
                'avg_processing_time' => rand(30, 120) // seconds
            ];
            $currentDate->addDay();
        }

        return $trends;
    }

    private function getQualityMetrics($startDate, $endDate): array
    {
        return [
            'accuracy_rate' => rand(92, 99),
            'compliance_rate' => rand(88, 98),
            'rework_rate' => rand(1, 8),
            'customer_complaints' => rand(0, 15),
            'quality_audits_passed' => rand(85, 100),
            'training_completion_rate' => rand(75, 95),
        ];
    }

    private function getCustomerSatisfactionData($startDate, $endDate): array
    {
        return [
            'overall_rating' => rand(3.8, 4.9),
            'response_time_rating' => rand(3.5, 4.8),
            'resolution_quality_rating' => rand(3.7, 4.7),
            'professionalism_rating' => rand(4.0, 4.9),
            'total_reviews' => rand(50, 300),
            'positive_feedback_percentage' => rand(75, 95),
        ];
    }

    private function getPerformanceGoals(): array
    {
        // Simulated performance goals
        return [
            [
                'id' => 1,
                'operator_id' => 'all',
                'metric' => 'success_rate',
                'target' => 95,
                'current' => 92.5,
                'period' => 'monthly',
                'description' => 'Maintain 95% transaction success rate',
                'status' => 'in_progress'
            ],
            [
                'id' => 2,
                'operator_id' => 'all',
                'metric' => 'avg_processing_time',
                'target' => 60,
                'current' => 75,
                'period' => 'monthly',
                'description' => 'Reduce average processing time to under 60 seconds',
                'status' => 'needs_attention'
            ]
        ];
    }

    private function getPerformanceAlerts(): array
    {
        $alerts = [];

        foreach ($this->performanceData as $operator) {
            // Check for performance issues
            if ($operator['success_rate'] < 90) {
                $alerts[] = [
                    'type' => 'warning',
                    'operator' => $operator['operator_name'],
                    'metric' => 'Success Rate',
                    'value' => $operator['success_rate'],
                    'threshold' => 90,
                    'message' => 'Success rate below 90%',
                    'priority' => 'medium'
                ];
            }

            if ($operator['productivity_score'] < 60) {
                $alerts[] = [
                    'type' => 'error',
                    'operator' => $operator['operator_name'],
                    'metric' => 'Productivity Score',
                    'value' => $operator['productivity_score'],
                    'threshold' => 60,
                    'message' => 'Productivity score critically low',
                    'priority' => 'high'
                ];
            }

            if ($operator['customer_satisfaction'] < 3.5) {
                $alerts[] = [
                    'type' => 'warning',
                    'operator' => $operator['operator_name'],
                    'metric' => 'Customer Satisfaction',
                    'value' => $operator['customer_satisfaction'],
                    'threshold' => 3.5,
                    'message' => 'Customer satisfaction below acceptable level',
                    'priority' => 'medium'
                ];
            }
        }

        return $alerts;
    }

    public function viewOperatorDetails(string $operatorId): void
    {
        $operatorData = collect($this->performanceData)->firstWhere('operator_id', $operatorId);

        if ($operatorData) {
            $this->selectedOperatorData = $operatorData;
            $this->showOperatorDetails = true;
        } else {
            $this->error('Operator data not found');
        }
    }

    public function closeOperatorDetails(): void
    {
        $this->showOperatorDetails = false;
        $this->selectedOperatorData = [];
    }

    public function generatePerformanceReport(): void
    {
        try {
            $reportData = [
                'report_type' => 'operator_performance',
                'date_range' => [
                    'from' => $this->dateFrom,
                    'to' => $this->dateTo
                ],
                'summary' => $this->performanceMetrics,
                'operators' => $this->performanceData,
                'top_performers' => $this->topPerformers,
                'productivity_trends' => $this->productivityTrends,
                'quality_metrics' => $this->qualityMetrics,
                'customer_satisfaction' => $this->customerSatisfaction,
                'performance_goals' => $this->performanceGoals,
                'alerts' => $this->alerts,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()->name ?? 'System'
            ];

            $this->dispatch('download-performance-report', $reportData);
            $this->success('Performance report generated successfully');
        } catch (\Exception $e) {
            $this->error('Report generation failed: ' . $e->getMessage());
        }
    }

    public function setPerformanceGoal(): void
    {
        $this->validate([
            'goalOperator' => 'required|string',
            'goalMetric' => 'required|string',
            'goalTarget' => 'required|numeric|min:0',
            'goalDescription' => 'required|string|min:10'
        ]);

        try {
            // In a real application, save to database
            $newGoal = [
                'id' => count($this->performanceGoals) + 1,
                'operator_id' => $this->goalOperator,
                'metric' => $this->goalMetric,
                'target' => $this->goalTarget,
                'period' => $this->goalPeriod,
                'description' => $this->goalDescription,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'created_by' => auth()->user()->name ?? 'System',
                'status' => 'active'
            ];

            $this->performanceGoals[] = $newGoal;
            $this->closeGoalSettings();
            $this->success('Performance goal set successfully');
        } catch (\Exception $e) {
            $this->error('Failed to set goal: ' . $e->getMessage());
        }
    }

    public function closeGoalSettings(): void
    {
        $this->showGoalSettings = false;
        $this->reset(['goalOperator', 'goalMetric', 'goalTarget', 'goalPeriod', 'goalDescription']);
    }

    public function with(): array
    {
        return [
            'metricOptions' => collect(self::PERFORMANCE_METRICS)->map(fn($name, $id) => [
                'id' => $id,
                'name' => $name
            ])->values()->toArray(),
            'departmentOptions' => collect(self::DEPARTMENTS)->map(fn($name, $id) => [
                'id' => $id,
                'name' => $name
            ])->values()->toArray(),
            'shiftOptions' => collect(self::SHIFT_TYPES)->map(fn($name, $id) => [
                'id' => $id,
                'name' => $name
            ])->values()->toArray(),
            'operatorOptions' => collect($this->operators)->map(fn($op) => [
                'id' => $op['id'],
                'name' => $op['name']
            ])->prepend(['id' => 'all', 'name' => 'All Operators'])->toArray()
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Operator Performance Analytics" subtitle="Monitor and analyze operator productivity and performance metrics" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-badge value="Period: {{ $dateFrom }} to {{ $dateTo }}" class="badge-info" />
                <x-badge value="Operators: {{ count($performanceData) }}" class="badge-neutral" />
                @if(count($alerts) > 0)
                <x-badge value="{{ count($alerts) }} Alerts" class="badge-warning" />
                @endif
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Set Goals"
                icon="o-flag"
                wire:click="$set('showGoalSettings', true)"
                class="btn-outline btn-sm" />

            <x-button
                label="Generate Report"
                icon="o-document-chart-bar"
                wire:click="generatePerformanceReport"
                class="btn-primary btn-sm"
                spinner="generatePerformanceReport" />

            <x-button
                label="Reset Filters"
                icon="o-arrow-path"
                wire:click="resetFilters"
                class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- PERFORMANCE ALERTS --}}
    @if(!empty($alerts))
    <x-card title="Performance Alerts" class="border-l-4 border-l-warning">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            @foreach(array_slice($alerts, 0, 6) as $alert)
            <div class="p-3 border rounded-lg {{ $alert['type'] === 'error' ? 'border-red-200 bg-red-50' : 'border-yellow-200 bg-yellow-50' }}">
                <div class="flex items-start gap-2">
                    <x-icon name="{{ $alert['type'] === 'error' ? 'o-exclamation-circle' : 'o-exclamation-triangle' }}"
                            class="w-4 h-4 {{ $alert['type'] === 'error' ? 'text-red-500' : 'text-yellow-500' }} mt-0.5" />
                    <div class="flex-1 text-sm">
                        <div class="font-medium">{{ $alert['operator'] }}</div>
                        <div class="{{ $alert['type'] === 'error' ? 'text-red-700' : 'text-yellow-700' }}">
                            {{ $alert['message'] }}
                        </div>
                        <div class="mt-1 text-xs text-gray-600">
                            {{ $alert['metric'] }}: {{ number_format($alert['value'], 1) }}{{ in_array($alert['metric'], ['Success Rate', 'Productivity Score']) ? '%' : '' }}
                            (Threshold: {{ $alert['threshold'] }}{{ in_array($alert['metric'], ['Success Rate', 'Productivity Score']) ? '%' : '' }})
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @if(count($alerts) > 6)
        <div class="mt-3 text-sm text-center text-gray-500">
            Showing 6 of {{ count($alerts) }} alerts
        </div>
        @endif
    </x-card>
    @endif

    {{-- FILTER CONTROLS --}}
    <x-card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            <x-datepicker
                label="From Date"
                wire:model.live="dateFrom"
                icon="o-calendar"
                :config="['altFormat' => 'd/m/Y', 'maxDate' => 'today']" />

            <x-datepicker
                label="To Date"
                wire:model.live="dateTo"
                icon="o-calendar"
                :config="['altFormat' => 'd/m/Y', 'maxDate' => 'today']" />

            <x-select
                label="Performance View"
                wire:model.live="performanceView"
                :options="[
                    ['id' => 'individual', 'name' => 'Individual'],
                    ['id' => 'team', 'name' => 'Team View'],
                    ['id' => 'department', 'name' => 'Department View']
                ]"
                option-value="id"
                option-label="name" />

            <x-select
                label="Department"
                wire:model.live="departmentFilter"
                :options="$departmentOptions"
                option-value="id"
                option-label="name"
                placeholder="All Departments" />

            <x-select
                label="Shift"
                wire:model.live="shiftFilter"
                :options="$shiftOptions"
                option-value="id"
                option-label="name"
                placeholder="All Shifts" />
        </div>
    </x-card>

    {{-- PERFORMANCE OVERVIEW CARDS --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
        <x-card class="stat-card">
            <x-stat
                title="Active Operators"
                value="{{ number_format($performanceMetrics['active_operators'] ?? 0) }}"
                icon="o-users"
                color="text-blue-500"
                description="Currently active" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Avg Productivity"
                value="{{ number_format($performanceMetrics['avg_productivity_score'] ?? 0, 1) }}%"
                icon="o-chart-bar"
                color="text-green-500"
                description="Team average" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Success Rate"
                value="{{ number_format($performanceMetrics['avg_success_rate'] ?? 0, 1) }}%"
                icon="o-check-circle"
                color="text-emerald-500"
                description="Transaction success" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Transactions"
                value="{{ number_format($performanceMetrics['total_transactions_processed'] ?? 0) }}"
                icon="o-queue-list"
                color="text-purple-500"
                description="Total processed" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="High Performers"
                value="{{ number_format($performanceMetrics['high_performers'] ?? 0) }}"
                icon="o-star"
                color="text-yellow-500"
                description="Score ≥ 80%" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Customer Rating"
                value="{{ number_format($performanceMetrics['avg_customer_satisfaction'] ?? 0, 1) }}/5"
                icon="o-heart"
                color="text-pink-500"
                description="Satisfaction" />
        </x-card>
    </div>

    {{-- TOP PERFORMERS --}}
    <x-card title="Top Performers" class="mb-6">
        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Operator</th>
                        <th>Department</th>
                        <th>Transactions</th>
                        <th>Success Rate</th>
                        <th>Productivity Score</th>
                        <th>Customer Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($topPerformers, 0, 10) as $index => $performer)
                    <tr class="hover:bg-base-200">
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-{{ $performer['productivity_score'] >= 80 ? 'green' : ($performer['productivity_score'] >= 60 ? 'yellow' : 'red') }}-600">
                                    {{ number_format($performer['productivity_score'], 1) }}
                                </span>
                                <div class="w-16 h-2 bg-gray-200 rounded-full">
                                    <div class="h-2 rounded-full {{ $performer['productivity_score'] >= 80 ? 'bg-green-500' : ($performer['productivity_score'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                         style="width: {{ min($performer['productivity_score'], 100) }}%"></div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="flex items-center gap-1">
                                @for($i = 1; $i <= 5; $i++)
                                <x-icon name="{{ $i <= $performer['customer_satisfaction'] ? 'o-star' : 'o-star' }}"
                                        class="w-3 h-3 {{ $i <= $performer['customer_satisfaction'] ? 'text-yellow-400' : 'text-gray-300' }}" />
                                @endfor
                                <span class="ml-1 text-sm">{{ number_format($performer['customer_satisfaction'], 1) }}</span>
                            </div>
                        </td>

                        <td>
                            <x-button
                                icon="o-eye"
                                wire:click="viewOperatorDetails('{{ $performer['operator_id'] }}')"
                                class="btn-ghost btn-xs"
                                tooltip="View Details" />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- PERFORMANCE METRICS GRID --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Productivity Trends Chart Placeholder --}}
        <x-card title="Productivity Trends">
            <div class="flex items-center justify-center h-64 mb-4 bg-gray-100 rounded-lg">
                <div class="text-center text-gray-500">
                    <x-icon name="o-chart-bar" class="w-8 h-8 mx-auto mb-2" />
                    <div>Productivity Trend Chart</div>
                    <div class="text-xs">Use Chart.js or similar library</div>
                </div>
            </div>

            {{-- Trend Summary --}}
            <div class="grid grid-cols-3 gap-4 text-center">
                @php
                    $avgProductivity = collect($productivityTrends)->avg('avg_productivity');
                    $totalTransactions = collect($productivityTrends)->sum('total_transactions');
                    $avgActiveOperators = collect($productivityTrends)->avg('active_operators');
                @endphp
                <div>
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($avgProductivity, 1) }}%</div>
                    <div class="text-sm text-gray-500">Avg Productivity</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">{{ number_format($totalTransactions) }}</div>
                    <div class="text-sm text-gray-500">Total Transactions</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-600">{{ number_format($avgActiveOperators, 0) }}</div>
                    <div class="text-sm text-gray-500">Avg Active Operators</div>
                </div>
            </div>
        </x-card>

        {{-- Quality Metrics --}}
        <x-card title="Quality Metrics">
            <div class="space-y-4">
                @foreach($qualityMetrics as $metric => $value)
                <div class="flex items-center justify-between">
                    <span class="font-medium capitalize">{{ str_replace('_', ' ', $metric) }}</span>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ number_format($value, 1) }}{{ in_array($metric, ['accuracy_rate', 'compliance_rate', 'quality_audits_passed', 'training_completion_rate', 'positive_feedback_percentage']) ? '%' : '' }}</span>
                        <div class="w-20 h-2 bg-gray-200 rounded-full">
                            <div class="h-2 rounded-full {{ $value >= 90 ? 'bg-green-500' : ($value >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ min($value, 100) }}%"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- PERFORMANCE GOALS --}}
    <x-card title="Performance Goals">
        <div class="space-y-4">
            @forelse($performanceGoals as $goal)
            <div class="p-4 border rounded-lg {{ $goal['status'] === 'needs_attention' ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200' }}">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="font-medium">{{ $goal['description'] }}</div>
                        <div class="mt-1 text-sm text-gray-600">
                            Target: {{ number_format($goal['target']) }}{{ in_array($goal['metric'], ['success_rate']) ? '%' : (in_array($goal['metric'], ['avg_processing_time']) ? 's' : '') }}
                            | Current: {{ number_format($goal['current']) }}{{ in_array($goal['metric'], ['success_rate']) ? '%' : (in_array($goal['metric'], ['avg_processing_time']) ? 's' : '') }}
                            | Period: {{ ucfirst($goal['period']) }}
                        </div>
                    </div>
                    <div class="ml-4 text-right">
                        @php
                            $progress = $goal['metric'] === 'avg_processing_time'
                                ? (($goal['target'] / $goal['current']) * 100)
                                : (($goal['current'] / $goal['target']) * 100);
                            $progress = min($progress, 100);
                        @endphp
                        <div class="font-semibold {{ $progress >= 90 ? 'text-green-600' : ($progress >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ number_format($progress, 1) }}%
                        </div>
                        <div class="w-24 h-2 mt-1 bg-gray-200 rounded-full">
                            <div class="h-2 rounded-full {{ $progress >= 90 ? 'bg-green-500' : ($progress >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="py-8 text-center text-gray-500">
                <x-icon name="o-flag" class="w-8 h-8 mx-auto mb-2" />
                <div>No performance goals set</div>
                <x-button
                    label="Set Your First Goal"
                    wire:click="$set('showGoalSettings', true)"
                    class="mt-2 btn-primary btn-sm" />
            </div>
            @endforelse
        </div>
    </x-card>

    {{-- ALL OPERATORS PERFORMANCE TABLE --}}
    <x-card title="All Operators Performance">
        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Operator</th>
                        <th>Department</th>
                        <th>Shift</th>
                        <th>Transactions</th>
                        <th>Success Rate</th>
                        <th>Avg Time</th>
                        <th>Productivity</th>
                        <th>Quality</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($performanceData as $operator)
                    <tr class="hover:bg-base-200">
                        <td>
                            <div class="font-medium">{{ $operator['operator_name'] }}</div>
                            <div class="text-sm text-gray-500">{{ $operator['operator_id'] }}</div>
                        </td>

                        <td>
                            <x-badge
                                value="{{ self::DEPARTMENTS[$operator['department']] ?? $operator['department'] }}"
                                class="badge-outline badge-sm" />
                        </td>

                        <td>
                            <span class="text-sm">{{ self::SHIFT_TYPES[$operator['shift']] ?? $operator['shift'] }}</span>
                        </td>

                        <td>
                            <div class="font-semibold">{{ number_format($operator['total_transactions']) }}</div>
                            <div class="text-xs text-gray-500">
                                {{ number_format($operator['total_volume'], 0) }} DJF
                            </div>
                        </td>

                        <td>
                            <span class="font-semibold {{ $operator['success_rate'] >= 95 ? 'text-green-600' : ($operator['success_rate'] >= 90 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($operator['success_rate'], 1) }}%
                            </span>
                        </td>

                        <td>
                            <span class="font-mono text-sm">{{ number_format($operator['avg_processing_time']) }}s</span>
                        </td>

                        <td>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold {{ $operator['productivity_score'] >= 80 ? 'text-green-600' : ($operator['productivity_score'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ number_format($operator['productivity_score'], 1) }}
                                </span>
                                @if($operator['productivity_score'] >= 80)
                                <x-badge value="High" class="badge-success badge-xs" />
                                @elseif($operator['productivity_score'] >= 60)
                                <x-badge value="Good" class="badge-warning badge-xs" />
                                @else
                                <x-badge value="Low" class="badge-error badge-xs" />
                                @endif
                            </div>
                        </td>

                        <td>
                            <span class="font-semibold {{ $operator['quality_score'] >= 95 ? 'text-green-600' : ($operator['quality_score'] >= 90 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($operator['quality_score'], 1) }}%
                            </span>
                        </td>

                        <td>
                            <div class="flex gap-1">
                                <x-button
                                    icon="o-eye"
                                    wire:click="viewOperatorDetails('{{ $operator['operator_id'] }}')"
                                    class="btn-ghost btn-xs"
                                    tooltip="View Details" />
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- OPERATOR DETAILS MODAL --}}
    <x-modal wire:model="showOperatorDetails" title="Operator Performance Details" class="max-w-4xl backdrop-blur">
        @if(!empty($selectedOperatorData))
        <div class="space-y-6">
            {{-- Operator Basic Info --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Operator Name</span>
                    </label>
                    <div class="font-medium">{{ $selectedOperatorData['operator_name'] }}</div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Department</span>
                    </label>
                    <x-badge
                        value="{{ self::DEPARTMENTS[$selectedOperatorData['department']] ?? $selectedOperatorData['department'] }}"
                        class="badge-outline" />
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Shift</span>
                    </label>
                    <div>{{ self::SHIFT_TYPES[$selectedOperatorData['shift']] ?? $selectedOperatorData['shift'] }}</div>
                </div>
            </div>

            {{-- Performance Metrics Grid --}}
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="p-4 text-center rounded-lg bg-blue-50">
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($selectedOperatorData['total_transactions']) }}</div>
                    <div class="text-sm text-gray-600">Total Transactions</div>
                </div>

                <div class="p-4 text-center rounded-lg bg-green-50">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($selectedOperatorData['success_rate'], 1) }}%</div>
                    <div class="text-sm text-gray-600">Success Rate</div>
                </div>

                <div class="p-4 text-center rounded-lg bg-purple-50">
                    <div class="text-2xl font-bold text-purple-600">{{ number_format($selectedOperatorData['productivity_score'], 1) }}</div>
                    <div class="text-sm text-gray-600">Productivity Score</div>
                </div>

                <div class="p-4 text-center rounded-lg bg-yellow-50">
                    <div class="text-2xl font-bold text-yellow-600">{{ number_format($selectedOperatorData['customer_satisfaction'], 1) }}/5</div>
                    <div class="text-sm text-gray-600">Customer Rating</div>
                </div>
            </div>

            {{-- Detailed Metrics --}}
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <h4 class="mb-3 font-semibold">Transaction Details</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Successful Transactions:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['successful_transactions']) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Failed Transactions:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['failed_transactions']) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Error Rate:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['error_rate'], 2) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Volume:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['total_volume'], 0) }} DJF</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Avg Transaction Value:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['avg_transaction_value'], 0) }} DJF</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="mb-3 font-semibold">Performance Metrics</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Avg Processing Time:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['avg_processing_time']) }}s</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Quality Score:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['quality_score'], 1) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Compliance Score:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['compliance_score'], 1) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Issues Resolved:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['issues_resolved']) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Avg Resolution Time:</span>
                            <span class="font-medium">{{ number_format($selectedOperatorData['avg_resolution_time']) }} min</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Training & Development --}}
            <div>
                <h4 class="mb-3 font-semibold">Training & Development</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-gray-50">
                        <div class="text-lg font-bold">{{ $selectedOperatorData['training_hours'] }}</div>
                        <div class="text-sm text-gray-600">Training Hours (This Period)</div>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50">
                        <div class="text-lg font-bold">{{ $selectedOperatorData['certifications'] }}</div>
                        <div class="text-sm text-gray-600">Active Certifications</div>
                    </div>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Close" wire:click="closeOperatorDetails" />
        </x-slot:actions>
        @endif
    </x-modal>

    {{-- GOAL SETTING MODAL --}}
    <x-modal wire:model="showGoalSettings" title="Set Performance Goal" class="max-w-2xl backdrop-blur">
        <form wire:submit="setPerformanceGoal">
            <div class="space-y-4">
                <x-select
                    label="Operator"
                    wire:model="goalOperator"
                    :options="$operatorOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="Select operator..."
                    required />

                <x-select
                    label="Metric"
                    wire:model="goalMetric"
                    :options="$metricOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="Select metric..."
                    required />

                <x-input
                    label="Target Value"
                    wire:model="goalTarget"
                    type="number"
                    step="0.1"
                    min="0"
                    placeholder="Enter target value..."
                    required />

                <x-select
                    label="Period"
                    wire:model="goalPeriod"
                    :options="[
                        ['id' => 'daily', 'name' => 'Daily'],
                        ['id' => 'weekly', 'name' => 'Weekly'],
                        ['id' => 'monthly', 'name' => 'Monthly'],
                        ['id' => 'quarterly', 'name' => 'Quarterly']
                    ]"
                    option-value="id"
                    option-label="name"
                    required />

                <x-textarea
                    label="Description"
                    wire:model="goalDescription"
                    placeholder="Describe the performance goal..."
                    rows="3"
                    required />
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="closeGoalSettings" />
                <x-button label="Set Goal" type="submit" class="btn-primary" spinner="setPerformanceGoal" />
            </x-slot:actions>
        </form>
    </x-modal>

    {{-- EXPORT SCRIPT --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('download-performance-report', (data) => {
                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `operator-performance-report-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        });
    </script>
</div>

