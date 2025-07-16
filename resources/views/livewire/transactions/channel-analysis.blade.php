<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $granularity = 'daily';
    public string $metric = 'volume';
    public string $comparisonPeriod = 'previous';

    // Make all data properties public so they're available in the view
    public array $trendData = [];
    public array $periodComparison = [];
    public array $growthRates = [];
    public array $seasonalPatterns = [];
    public array $movingAverages = [];
    public array $trendIndicators = [];
    public array $forecastData = [];
    public array $insights = [];

    public function mount(): void
    {
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');

        // Initialize with empty data structure first
        $this->resetToEmptyData();

        // Then load actual data
        $this->loadAnalysisData();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['dateFrom', 'dateTo', 'granularity', 'metric', 'comparisonPeriod'])) {
            $this->loadAnalysisData();
        }
    }

    public function loadAnalysisData(): void
    {
        try {
            $analysisData = $this->getAnalysisData();

            // Assign to public properties
            $this->trendData = $analysisData['trendData'] ?? [];
            $this->periodComparison = $analysisData['periodComparison'] ?? [];
            $this->growthRates = $analysisData['growthRates'] ?? [];
            $this->seasonalPatterns = $analysisData['seasonalPatterns'] ?? [];
            $this->movingAverages = $analysisData['movingAverages'] ?? [];
            $this->trendIndicators = $analysisData['trendIndicators'] ?? [];
            $this->forecastData = $analysisData['forecastData'] ?? [];
            $this->insights = $this->generateInsights();
        } catch (\Exception $e) {
            logger()->error('Trend analysis error: ' . $e->getMessage());
            $this->error('Error loading trend analysis data. Please try again.');
            $this->resetToEmptyData();
        }
    }

    private function resetToEmptyData(): void
    {
        $this->trendData = [];
        $this->periodComparison = [
            'current' => [],
            'previous' => [],
            'comparison' => []
        ];
        $this->growthRates = [];
        $this->seasonalPatterns = [
            'day_of_week' => [],
            'hour_of_day' => [],
            'month' => []
        ];
        $this->movingAverages = [];
        $this->trendIndicators = [
            'volume_trend' => 'insufficient_data',
            'count_trend' => 'insufficient_data',
            'success_rate_trend' => 'insufficient_data',
            'volatility' => 0,
            'correlation_volume_count' => 0
        ];
        $this->forecastData = [
            'projections' => [],
            'regression' => [],
            'r_squared' => 0
        ];
        $this->insights = [];
    }

    private function getAnalysisData(): array
    {
        $startDate = Carbon::parse($this->dateFrom);
        $endDate = Carbon::parse($this->dateTo);

        // Main trend analysis
        $trendData = $this->getTrendData($startDate, $endDate);

        // Period comparison
        $periodComparison = $this->getPeriodComparison($startDate, $endDate);

        // Growth rates
        $growthRates = $this->getGrowthRates($startDate, $endDate);

        // Seasonal patterns
        $seasonalPatterns = $this->getSeasonalPatterns($startDate, $endDate);

        // Moving averages
        $movingAverages = $this->getMovingAverages($startDate, $endDate);

        // Trend indicators
        $trendIndicators = $this->getTrendIndicators($startDate, $endDate);

        // Forecast data (basic linear projection)
        $forecastData = $this->getForecastData($trendData);

        return [
            'trendData' => $trendData,
            'periodComparison' => $periodComparison,
            'growthRates' => $growthRates,
            'seasonalPatterns' => $seasonalPatterns,
            'movingAverages' => $movingAverages,
            'trendIndicators' => $trendIndicators,
            'forecastData' => $forecastData,
        ];
    }

    private function getTrendData($startDate, $endDate): array
    {
        $groupByClause = match($this->granularity) {
            'weekly' => "DATE_TRUNC('week', trans_initate_time)",
            'monthly' => "DATE_TRUNC('month', trans_initate_time)",
            default => "DATE(trans_initate_time)"
        };

        $dateFormat = match($this->granularity) {
            'weekly' => 'Y-\\WW',
            'monthly' => 'Y-m',
            default => 'Y-m-d'
        };

        $results = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select(
                DB::raw("$groupByClause as period"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_amount) as total_volume'),
                DB::raw('AVG(actual_amount) as avg_amount'),
                DB::raw('SUM(fee) as total_fees'),
                DB::raw('COUNT(CASE WHEN trans_status = \'Completed\' THEN 1 END) as successful_count'),
                DB::raw('COUNT(CASE WHEN trans_status = \'Failed\' THEN 1 END) as failed_count'),
                DB::raw('SUM(CASE WHEN actual_amount >= 10000 THEN 1 ELSE 0 END) as high_value_count'),
                DB::raw('SUM(CASE WHEN is_reversed = 1 THEN 1 ELSE 0 END) as reversed_count')
            )
            ->groupBy(DB::raw($groupByClause))
            ->orderBy(DB::raw($groupByClause))
            ->get();

        return $results->map(function($item) use ($dateFormat) {
            $successRate = $item->transaction_count > 0 ? ($item->successful_count / $item->transaction_count) * 100 : 0;
            $failureRate = $item->transaction_count > 0 ? ($item->failed_count / $item->transaction_count) * 100 : 0;
            $reversalRate = $item->transaction_count > 0 ? ($item->reversed_count / $item->transaction_count) * 100 : 0;

            return [
                'period' => $item->period,
                'formatted_period' => Carbon::parse($item->period)->format($dateFormat),
                'transaction_count' => $item->transaction_count,
                'total_volume' => (float)$item->total_volume,
                'avg_amount' => (float)$item->avg_amount,
                'total_fees' => (float)$item->total_fees,
                'successful_count' => $item->successful_count,
                'failed_count' => $item->failed_count,
                'high_value_count' => $item->high_value_count,
                'reversed_count' => $item->reversed_count,
                'success_rate' => round($successRate, 2),
                'failure_rate' => round($failureRate, 2),
                'reversal_rate' => round($reversalRate, 2),
            ];
        })->toArray();
    }

    private function getPeriodComparison($startDate, $endDate): array
    {
        $currentPeriodDays = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($currentPeriodDays);
        $previousEndDate = $startDate->copy()->subDay();

        $currentPeriod = $this->getPeriodStats($startDate, $endDate);
        $previousPeriod = $this->getPeriodStats($previousStartDate, $previousEndDate);

        return [
            'current' => $currentPeriod,
            'previous' => $previousPeriod,
            'comparison' => $this->calculateComparison($currentPeriod, $previousPeriod)
        ];
    }

    private function getPeriodStats($startDate, $endDate): array
    {
        $result = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount,
                SUM(fee) as total_fees,
                COUNT(CASE WHEN trans_status = \'Completed\' THEN 1 END) as successful_count,
                COUNT(CASE WHEN trans_status = \'Failed\' THEN 1 END) as failed_count,
                SUM(CASE WHEN actual_amount >= 10000 THEN 1 ELSE 0 END) as high_value_count,
                SUM(CASE WHEN is_reversed = 1 THEN 1 ELSE 0 END) as reversed_count
            ')
            ->first();

        $totalTransactions = $result->total_transactions ?? 0;
        $successRate = $totalTransactions > 0 ? (($result->successful_count ?? 0) / $totalTransactions) * 100 : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_volume' => (float)($result->total_volume ?? 0),
            'avg_amount' => (float)($result->avg_amount ?? 0),
            'total_fees' => (float)($result->total_fees ?? 0),
            'successful_count' => $result->successful_count ?? 0,
            'failed_count' => $result->failed_count ?? 0,
            'high_value_count' => $result->high_value_count ?? 0,
            'reversed_count' => $result->reversed_count ?? 0,
            'success_rate' => round($successRate, 2),
        ];
    }

    private function calculateComparison($current, $previous): array
    {
        $comparison = [];

        foreach ($current as $key => $value) {
            if (isset($previous[$key]) && $previous[$key] != 0) {
                $change = (($value - $previous[$key]) / $previous[$key]) * 100;
                $comparison[$key . '_change'] = round($change, 2);
                $comparison[$key . '_change_abs'] = $value - $previous[$key];
                $comparison[$key . '_trend'] = $change > 5 ? 'up' : ($change < -5 ? 'down' : 'stable');
            } else {
                $comparison[$key . '_change'] = $value > 0 ? 100 : 0;
                $comparison[$key . '_change_abs'] = $value;
                $comparison[$key . '_trend'] = $value > 0 ? 'up' : 'stable';
            }
        }

        return $comparison;
    }

    private function getGrowthRates($startDate, $endDate): array
    {
        $trendData = collect($this->getTrendData($startDate, $endDate));

        if ($trendData->count() < 2) {
            return [];
        }

        $growthRates = [];
        $metrics = ['transaction_count', 'total_volume', 'avg_amount', 'success_rate'];

        foreach ($metrics as $metric) {
            $values = $trendData->pluck($metric)->toArray();
            $periods = count($values);
            $growthRate = 0;

            if ($periods > 1 && $values[0] != 0) {
                // Calculate compound growth rate
                $growthRate = (pow(end($values) / $values[0], 1/($periods-1)) - 1) * 100;
            }

            $growthRates[$metric] = [
                'growth_rate' => round($growthRate, 2),
                'start_value' => $values[0] ?? 0,
                'end_value' => end($values) ?: 0,
                'total_change' => (end($values) ?: 0) - ($values[0] ?? 0),
                'periods' => $periods
            ];
        }

        return $growthRates;
    }

    private function getSeasonalPatterns($startDate, $endDate): array
    {
        // Day of week patterns
        $dayOfWeekPattern = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select(
                DB::raw('EXTRACT(DOW FROM trans_initate_time) as day_of_week'),
                DB::raw('TO_CHAR(trans_initate_time, \'Day\') as day_name'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_amount) as total_volume'),
                DB::raw('AVG(actual_amount) as avg_amount')
            )
            ->groupBy(DB::raw('EXTRACT(DOW FROM trans_initate_time)'), DB::raw('TO_CHAR(trans_initate_time, \'Day\')'))
            ->orderBy('day_of_week')
            ->get()
            ->map(function($item) {
                return [
                    'day_of_week' => $item->day_of_week,
                    'day_name' => trim($item->day_name),
                    'transaction_count' => $item->transaction_count,
                    'total_volume' => (float)$item->total_volume,
                    'avg_amount' => (float)$item->avg_amount,
                ];
            })
            ->toArray();

        // Hour of day patterns
        $hourPattern = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select(
                DB::raw('EXTRACT(HOUR FROM trans_initate_time) as hour'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_amount) as total_volume'),
                DB::raw('AVG(actual_amount) as avg_amount')
            )
            ->groupBy(DB::raw('EXTRACT(HOUR FROM trans_initate_time)'))
            ->orderBy('hour')
            ->get()
            ->map(function($item) {
                return [
                    'hour' => (int)$item->hour,
                    'transaction_count' => $item->transaction_count,
                    'total_volume' => (float)$item->total_volume,
                    'avg_amount' => (float)$item->avg_amount,
                ];
            })
            ->toArray();

        // Month patterns (if data spans multiple months)
        $monthPattern = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select(
                DB::raw('EXTRACT(MONTH FROM trans_initate_time) as month'),
                DB::raw('TO_CHAR(trans_initate_time, \'Month\') as month_name'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_amount) as total_volume'),
                DB::raw('AVG(actual_amount) as avg_amount')
            )
            ->groupBy(DB::raw('EXTRACT(MONTH FROM trans_initate_time)'), DB::raw('TO_CHAR(trans_initate_time, \'Month\')'))
            ->orderBy('month')
            ->get()
            ->map(function($item) {
                return [
                    'month' => $item->month,
                    'month_name' => trim($item->month_name),
                    'transaction_count' => $item->transaction_count,
                    'total_volume' => (float)$item->total_volume,
                    'avg_amount' => (float)$item->avg_amount,
                ];
            })
            ->toArray();

        return [
            'day_of_week' => $dayOfWeekPattern,
            'hour_of_day' => $hourPattern,
            'month' => $monthPattern
        ];
    }

    private function getMovingAverages($startDate, $endDate): array
    {
        $trendData = collect($this->getTrendData($startDate, $endDate));
        $windowSizes = [7, 14, 30]; // Moving average windows
        $movingAverages = [];

        foreach ($windowSizes as $window) {
            $movingAverages[$window . '_day'] = $this->calculateMovingAverage(
                $trendData->pluck('total_volume')->toArray(),
                $window
            );
        }

        return $movingAverages;
    }

    private function calculateMovingAverage(array $data, int $window): array
    {
        $movingAverage = [];
        $count = count($data);

        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $window + 1);
            $values = array_slice($data, $start, $i - $start + 1);
            $movingAverage[] = count($values) > 0 ? array_sum($values) / count($values) : 0;
        }

        return $movingAverage;
    }

    private function getTrendIndicators($startDate, $endDate): array
    {
        $trendData = collect($this->getTrendData($startDate, $endDate));

        if ($trendData->count() < 3) {
            return ['trend_direction' => 'insufficient_data'];
        }

        $volumes = $trendData->pluck('total_volume')->toArray();
        $counts = $trendData->pluck('transaction_count')->toArray();
        $successRates = $trendData->pluck('success_rate')->toArray();

        return [
            'volume_trend' => $this->determineTrendDirection($volumes),
            'count_trend' => $this->determineTrendDirection($counts),
            'success_rate_trend' => $this->determineTrendDirection($successRates),
            'volatility' => $this->calculateVolatility($volumes),
            'correlation_volume_count' => $this->calculateCorrelation($volumes, $counts)
        ];
    }

    private function determineTrendDirection(array $data): string
    {
        if (count($data) < 3) return 'insufficient_data';

        $increases = 0;
        $decreases = 0;

        for ($i = 1; $i < count($data); $i++) {
            if ($data[$i] > $data[$i-1]) $increases++;
            elseif ($data[$i] < $data[$i-1]) $decreases++;
        }

        if ($increases > $decreases * 1.5) return 'upward';
        elseif ($decreases > $increases * 1.5) return 'downward';
        else return 'stable';
    }

    private function calculateVolatility(array $data): float
    {
        if (count($data) < 2) return 0;

        $mean = array_sum($data) / count($data);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $data)) / count($data);

        return $mean > 0 ? sqrt($variance) / $mean : 0;
    }

    private function calculateCorrelation(array $x, array $y): float
    {
        $n = min(count($x), count($y));
        if ($n < 2) return 0;

        $meanX = array_sum(array_slice($x, 0, $n)) / $n;
        $meanY = array_sum(array_slice($y, 0, $n)) / $n;

        $numerator = 0;
        $sumSqX = 0;
        $sumSqY = 0;

        for ($i = 0; $i < $n; $i++) {
            $diffX = $x[$i] - $meanX;
            $diffY = $y[$i] - $meanY;

            $numerator += $diffX * $diffY;
            $sumSqX += $diffX * $diffX;
            $sumSqY += $diffY * $diffY;
        }

        $denominator = sqrt($sumSqX * $sumSqY);
        return $denominator > 0 ? $numerator / $denominator : 0;
    }

    private function getForecastData(array $trendData): array
    {
        if (count($trendData) < 3) {
            return ['error' => 'Insufficient data for forecasting'];
        }

        $volumes = array_column($trendData, 'total_volume');
        $periods = range(1, count($volumes));

        // Simple linear regression for forecasting
        $forecast = $this->linearRegression($periods, $volumes);

        // Project next 7 periods
        $projections = [];
        for ($i = 1; $i <= 7; $i++) {
            $nextPeriod = count($volumes) + $i;
            $projectedValue = $forecast['slope'] * $nextPeriod + $forecast['intercept'];
            $projections[] = [
                'period' => $nextPeriod,
                'projected_volume' => max(0, $projectedValue), // Ensure non-negative
                'confidence' => max(0, 1 - ($i * 0.1)) // Decreasing confidence
            ];
        }

        return [
            'regression' => $forecast,
            'projections' => $projections,
            'r_squared' => $forecast['r_squared']
        ];
    }

    private function linearRegression(array $x, array $y): array
    {
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Calculate R-squared
        $meanY = $sumY / $n;
        $ssTotal = 0;
        $ssRes = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $x[$i] + $intercept;
            $ssTotal += pow($y[$i] - $meanY, 2);
            $ssRes += pow($y[$i] - $predicted, 2);
        }

        $rSquared = $ssTotal > 0 ? 1 - ($ssRes / $ssTotal) : 0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared
        ];
    }

    private function generateInsights(): array
    {
        $insights = [];

        // Volume trend insight
        if (!empty($this->trendIndicators['volume_trend'])) {
            $trend = $this->trendIndicators['volume_trend'];
            switch ($trend) {
                case 'upward':
                    $insights[] = [
                        'type' => 'positive',
                        'title' => 'Growing Transaction Volume',
                        'description' => 'Transaction volume shows an upward trend over the selected period.',
                        'icon' => 'o-arrow-trending-up'
                    ];
                    break;
                case 'downward':
                    $insights[] = [
                        'type' => 'warning',
                        'title' => 'Declining Transaction Volume',
                        'description' => 'Transaction volume shows a downward trend. Consider investigating causes.',
                        'icon' => 'o-arrow-trending-down'
                    ];
                    break;
                case 'stable':
                    $insights[] = [
                        'type' => 'neutral',
                        'title' => 'Stable Transaction Volume',
                        'description' => 'Transaction volume remains relatively stable over the period.',
                        'icon' => 'o-minus'
                    ];
                    break;
            }
        }

        // Success rate insight
        if (!empty($this->periodComparison['current']['success_rate'])) {
            $successRate = $this->periodComparison['current']['success_rate'];
            if ($successRate >= 95) {
                $insights[] = [
                    'type' => 'positive',
                    'title' => 'Excellent Success Rate',
                    'description' => "Current success rate of {$successRate}% is excellent.",
                    'icon' => 'o-check-circle'
                ];
            } elseif ($successRate < 90) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Low Success Rate',
                    'description' => "Success rate of {$successRate}% needs attention.",
                    'icon' => 'o-exclamation-triangle'
                ];
            }
        }

        // High volatility warning
        if (!empty($this->trendIndicators['volatility']) && $this->trendIndicators['volatility'] > 0.3) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'High Volatility Detected',
                'description' => 'Transaction volumes show high volatility. Consider investigating irregular patterns.',
                'icon' => 'o-chart-bar'
            ];
        }

        // Strong correlation insight
        if (!empty($this->trendIndicators['correlation_volume_count']) && abs($this->trendIndicators['correlation_volume_count']) > 0.8) {
            $correlation = $this->trendIndicators['correlation_volume_count'];
            $insights[] = [
                'type' => 'info',
                'title' => 'Strong Volume-Count Correlation',
                'description' => sprintf('Transaction volume and count show %s correlation (%.2f).',
                    $correlation > 0 ? 'positive' : 'negative', $correlation),
                'icon' => 'o-arrow-path'
            ];
        }

        return $insights;
    }

    public function exportTrendData(): void
    {
        try {
            $exportData = [
                'export_type' => 'trend_analysis',
                'date_range' => [
                    'from' => $this->dateFrom,
                    'to' => $this->dateTo
                ],
                'granularity' => $this->granularity,
                'metric' => $this->metric,
                'trend_data' => $this->trendData,
                'period_comparison' => $this->periodComparison,
                'growth_rates' => $this->growthRates,
                'seasonal_patterns' => $this->seasonalPatterns,
                'trend_indicators' => $this->trendIndicators,
                'forecast_data' => $this->forecastData,
                'insights' => $this->insights,
                'generated_at' => now()->toISOString(),
                'generated_by' => auth()->user()->name ?? 'System'
            ];

            $this->dispatch('download-trend-export', $exportData);
            $this->success('Trend analysis export initiated.');
        } catch (\Exception $e) {
            $this->error('Export failed: ' . $e->getMessage());
        }
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->granularity = 'daily';
        $this->metric = 'volume';
        $this->comparisonPeriod = 'previous';
        $this->loadAnalysisData();
        $this->success('Filters reset successfully');
    }

    private function getTrendColor(string $trend): string
    {
        return match($trend) {
            'upward' => 'text-green-600',
            'downward' => 'text-red-600',
            'stable' => 'text-blue-600',
            default => 'text-gray-600'
        };
    }

    private function getTrendIcon(string $trend): string
    {
        return match($trend) {
            'upward' => 'o-arrow-trending-up',
            'downward' => 'o-arrow-trending-down',
            'stable' => 'o-minus',
            default => 'o-question-mark-circle'
        };
    }

    private function getInsightIcon(string $type): string
    {
        return match($type) {
            'positive' => 'o-check-circle',
            'warning' => 'o-exclamation-triangle',
            'info' => 'o-information-circle',
            default => 'o-light-bulb'
        };
    }

    private function getInsightColor(string $type): string
    {
        return match($type) {
            'positive' => 'border-green-200 bg-green-50 text-green-800',
            'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
            'info' => 'border-blue-200 bg-blue-50 text-blue-800',
            default => 'border-gray-200 bg-gray-50 text-gray-800'
        };
    }
}; ?>
<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Transaction Trend Analysis" subtitle="Advanced analytics and forecasting for transaction patterns" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-badge value="Period: {{ $dateFrom }} to {{ $dateTo }}" class="badge-info" />
                <x-badge value="Granularity: {{ ucfirst($granularity) }}" class="badge-neutral" />
                <x-badge value="Metric: {{ ucfirst($metric) }}" class="badge-primary" />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Export Data"
                icon="o-arrow-down-tray"
                wire:click="exportTrendData"
                class="btn-outline btn-sm"
                spinner="exportTrendData" />

            <x-button
                label="Reset Filters"
                icon="o-arrow-path"
                wire:click="resetFilters"
                class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

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
                label="Granularity"
                wire:model.live="granularity"
                :options="[
                    ['id' => 'daily', 'name' => 'Daily'],
                    ['id' => 'weekly', 'name' => 'Weekly'],
                    ['id' => 'monthly', 'name' => 'Monthly']
                ]"
                option-value="id"
                option-label="name" />

            <x-select
                label="Primary Metric"
                wire:model.live="metric"
                :options="[
                    ['id' => 'volume', 'name' => 'Transaction Volume'],
                    ['id' => 'count', 'name' => 'Transaction Count'],
                    ['id' => 'success_rate', 'name' => 'Success Rate'],
                    ['id' => 'avg_amount', 'name' => 'Average Amount']
                ]"
                option-value="id"
                option-label="name" />

            <x-select
                label="Comparison Period"
                wire:model.live="comparisonPeriod"
                :options="[
                    ['id' => 'previous', 'name' => 'Previous Period'],
                    ['id' => 'year_over_year', 'name' => 'Year over Year'],
                    ['id' => 'month_over_month', 'name' => 'Month over Month']
                ]"
                option-value="id"
                option-label="name" />
        </div>
    </x-card>

    {{-- INSIGHTS ALERTS --}}
    @if(!empty($insights))
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($insights as $insight)
        <x-alert class="{{ $this->getInsightColor($insight['type']) }}">
            <div class="flex items-start gap-3">
                <x-icon name="{{ $insight['icon'] }}" class="w-5 h-5 mt-0.5" />
                <div>
                    <div class="font-semibold">{{ $insight['title'] }}</div>
                    <div class="text-sm">{{ $insight['description'] }}</div>
                </div>
            </div>
        </x-alert>
        @endforeach
    </div>
    @endif

    {{-- SUMMARY STATISTICS --}}
    @if(!empty($periodComparison['current']))
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
        <x-card class="stat-card">
            <x-stat
                title="Total Transactions"
                value="{{ number_format($periodComparison['current']['total_transactions']) }}"
                icon="o-queue-list"
                color="text-blue-500"
                @if(!empty($periodComparison['comparison']['total_transactions_change']))
                description="{{ $periodComparison['comparison']['total_transactions_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['total_transactions_change'], 1) }}% vs previous"
                @endif />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Total Volume"
                value="{{ number_format($periodComparison['current']['total_volume'], 0) }} DJF"
                icon="o-banknotes"
                color="text-green-500"
                @if(!empty($periodComparison['comparison']['total_volume_change']))
                description="{{ $periodComparison['comparison']['total_volume_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['total_volume_change'], 1) }}% vs previous"
                @endif />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Average Amount"
                value="{{ number_format($periodComparison['current']['avg_amount'], 0) }} DJF"
                icon="o-calculator"
                color="text-purple-500"
                @if(!empty($periodComparison['comparison']['avg_amount_change']))
                description="{{ $periodComparison['comparison']['avg_amount_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['avg_amount_change'], 1) }}% vs previous"
                @endif />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Success Rate"
                value="{{ number_format($periodComparison['current']['success_rate'], 1) }}%"
                icon="o-check-circle"
                color="text-emerald-500"
                @if(!empty($periodComparison['comparison']['success_rate_change']))
                description="{{ $periodComparison['comparison']['success_rate_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['success_rate_change'], 1) }}% vs previous"
                @endif />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="High Value Txns"
                value="{{ number_format($periodComparison['current']['high_value_count']) }}"
                icon="o-star"
                color="text-yellow-500"
                @if(!empty($periodComparison['comparison']['high_value_count_change']))
                description="{{ $periodComparison['comparison']['high_value_count_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['high_value_count_change'], 1) }}% vs previous"
                @endif />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Total Fees"
                value="{{ number_format($periodComparison['current']['total_fees'], 0) }} DJF"
                icon="o-currency-dollar"
                color="text-indigo-500"
                @if(!empty($periodComparison['comparison']['total_fees_change']))
                description="{{ $periodComparison['comparison']['total_fees_change'] >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['total_fees_change'], 1) }}% vs previous"
                @endif />
        </x-card>
    </div>
    @endif

    {{-- TREND INDICATORS --}}
    @if(!empty($trendIndicators))
    <x-card title="Trend Indicators" class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="p-4 text-center border rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <x-icon name="{{ $this->getTrendIcon($trendIndicators['volume_trend']) }}"
                            class="w-6 h-6 {{ $this->getTrendColor($trendIndicators['volume_trend']) }}" />
                </div>
                <div class="font-semibold">Volume Trend</div>
                <div class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $trendIndicators['volume_trend']) }}</div>
            </div>

            <div class="p-4 text-center border rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <x-icon name="{{ $this->getTrendIcon($trendIndicators['count_trend']) }}"
                            class="w-6 h-6 {{ $this->getTrendColor($trendIndicators['count_trend']) }}" />
                </div>
                <div class="font-semibold">Count Trend</div>
                <div class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $trendIndicators['count_trend']) }}</div>
            </div>

            <div class="p-4 text-center border rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <x-icon name="o-chart-bar" class="w-6 h-6 text-orange-500" />
                </div>
                <div class="font-semibold">Volatility</div>
                <div class="text-sm text-gray-600">{{ number_format($trendIndicators['volatility'], 3) }}</div>
            </div>

            <div class="p-4 text-center border rounded-lg">
                <div class="flex items-center justify-center mb-2">
                    <x-icon name="o-arrow-path" class="w-6 h-6 text-purple-500" />
                </div>
                <div class="font-semibold">Volume-Count Correlation</div>
                <div class="text-sm text-gray-600">{{ number_format($trendIndicators['correlation_volume_count'], 3) }}</div>
            </div>
        </div>
    </x-card>
    @endif

    {{-- MAIN TREND CHART PLACEHOLDER --}}
    <x-card title="Transaction Trend Over Time">
        @if(!empty($trendData))
        <div class="mb-4 text-sm text-gray-600">
            Showing {{ count($trendData) }} data points from {{ $dateFrom }} to {{ $dateTo }}
        </div>

        {{-- Chart would go here - This is a placeholder for your charting library --}}
        <div class="flex items-center justify-center h-64 bg-gray-100 rounded-lg">
            <div class="text-center text-gray-500">
                <x-icon name="o-chart-bar" class="w-8 h-8 mx-auto mb-2" />
                <div>Chart visualization would be rendered here</div>
                <div class="text-xs">Use Chart.js, ApexCharts, or similar library</div>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="mt-6 overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Transactions</th>
                        <th>Volume (DJF)</th>
                        <th>Avg Amount</th>
                        <th>Success Rate</th>
                        <th>Fees (DJF)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($trendData, -10) as $item)
                    <tr>
                        <td class="font-mono text-sm">{{ $item['formatted_period'] }}</td>
                        <td>{{ number_format($item['transaction_count']) }}</td>
                        <td>{{ number_format($item['total_volume'], 0) }}</td>
                        <td>{{ number_format($item['avg_amount'], 0) }}</td>
                        <td>
                            <span class="px-2 py-1 text-xs rounded {{ $item['success_rate'] >= 95 ? 'bg-green-100 text-green-800' : ($item['success_rate'] >= 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ number_format($item['success_rate'], 1) }}%
                            </span>
                        </td>
                        <td>{{ number_format($item['total_fees'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if(count($trendData) > 10)
            <div class="mt-2 text-sm text-center text-gray-500">
                Showing last 10 periods. Total: {{ count($trendData) }} periods.
            </div>
            @endif
        </div>
        @else
        <div class="py-8 text-center text-gray-500">
            <x-icon name="o-chart-bar" class="w-8 h-8 mx-auto mb-2" />
            <div>No trend data available for the selected period</div>
        </div>
        @endif
    </x-card>

    {{-- SEASONAL PATTERNS --}}
    @if(!empty($seasonalPatterns))
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Day of Week Pattern --}}
        @if(!empty($seasonalPatterns['day_of_week']))
        <x-card title="Day of Week Patterns">
            <div class="space-y-3">
                @php
                    $maxDayVolume = collect($seasonalPatterns['day_of_week'])->max('transaction_count') ?: 1;
                @endphp
                @foreach($seasonalPatterns['day_of_week'] as $day)
                    @php
                        $percentage = ($day['transaction_count'] / $maxDayVolume) * 100;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium">{{ $day['day_name'] }}</span>
                            <span class="text-sm text-gray-500">
                                {{ number_format($day['transaction_count']) }} txns
                            </span>
                        </div>
                        <div class="w-full h-3 bg-gray-200 rounded-full">
                            <div class="h-3 transition-all duration-300 bg-blue-500 rounded-full"
                                 style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ number_format($day['total_volume'], 0) }} DJF
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
        @endif

        {{-- Hourly Pattern --}}
        @if(!empty($seasonalPatterns['hour_of_day']))
        <x-card title="Hourly Usage Patterns">
            <div class="grid grid-cols-12 gap-1 mb-4">
                @php
                    $maxHourVolume = collect($seasonalPatterns['hour_of_day'])->max('transaction_count') ?: 1;
                @endphp
                @for($hour = 0; $hour < 24; $hour++)
                    @php
                        $hourData = collect($seasonalPatterns['hour_of_day'])->firstWhere('hour', $hour);
                        $count = $hourData['transaction_count'] ?? 0;
                        $height = ($count / $maxHourVolume) * 100;
                    @endphp
                    <div class="text-center">
                        <div class="flex items-end justify-center h-12">
                            <div
                                class="w-full transition-all bg-green-500 rounded-t hover:bg-green-600"
                                style="height: {{ $height }}%"
                                title="Hour {{ $hour }}:00 - {{ $count }} transactions">
                            </div>
                        </div>
                        @if($hour % 6 === 0)
                            <div class="mt-1 text-xs text-gray-500">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</div>
                        @endif
                    </div>
                @endfor
            </div>
            <div class="text-xs text-center text-gray-500">
                Peak hours show higher transaction volumes
            </div>
        </x-card>
        @endif
    </div>
    @endif

    {{-- GROWTH RATES --}}
    @if(!empty($growthRates))
    <x-card title="Growth Rate Analysis">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            @foreach($growthRates as $metric => $growth)
            <div class="p-4 border rounded-lg">
                <div class="mb-2 font-semibold capitalize">{{ str_replace('_', ' ', $metric) }}</div>
                <div class="text-2xl font-bold {{ $growth['growth_rate'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $growth['growth_rate'] >= 0 ? '+' : '' }}{{ number_format($growth['growth_rate'], 2) }}%
                </div>
                <div class="mt-1 text-sm text-gray-500">
                    From {{ is_numeric($growth['start_value']) ? number_format($growth['start_value'], 0) : $growth['start_value'] }}
                    to {{ is_numeric($growth['end_value']) ? number_format($growth['end_value'], 0) : $growth['end_value'] }}
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    Over {{ $growth['periods'] }} periods
                </div>
            </div>
            @endforeach
        </div>
    </x-card>
    @endif

    {{-- FORECAST DATA --}}
    @if(!empty($forecastData['projections']))
    <x-card title="7-Day Forecast">
        <div class="mb-4">
            <div class="text-sm text-gray-600">
                Based on linear regression analysis
                (R² = {{ number_format($forecastData['r_squared'], 3) }})
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Future Period</th>
                        <th>Projected Volume (DJF)</th>
                        <th>Confidence Level</th>
                        <th>Reliability</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($forecastData['projections'] as $projection)
                    <tr>
                        <td class="font-mono">Period +{{ $projection['period'] - count($trendData) }}</td>
                        <td>{{ number_format($projection['projected_volume'], 0) }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <span>{{ number_format($projection['confidence'] * 100, 1) }}%</span>
                                <div class="w-16 h-2 bg-gray-200 rounded-full">
                                    <div class="h-2 bg-blue-500 rounded-full"
                                         style="width: {{ $projection['confidence'] * 100 }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="px-2 py-1 text-xs rounded {{ $projection['confidence'] >= 0.8 ? 'bg-green-100 text-green-800' : ($projection['confidence'] >= 0.6 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ $projection['confidence'] >= 0.8 ? 'High' : ($projection['confidence'] >= 0.6 ? 'Medium' : 'Low') }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3 mt-4 border border-yellow-200 rounded-lg bg-yellow-50">
            <div class="flex items-start gap-2">
                <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-yellow-600 mt-0.5" />
                <div class="text-sm text-yellow-800">
                    <strong>Disclaimer:</strong> Forecasts are based on historical trends and should be used as guidance only.
                    Actual results may vary due to external factors not captured in the model.
                </div>
            </div>
        </div>
    </x-card>
    @endif

    {{-- EXPORT SCRIPT --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('download-trend-export', (data) => {
                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `trend-analysis-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        });
    </script>
</div>
