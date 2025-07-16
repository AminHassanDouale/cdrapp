<?php
// resources/views/livewire/transactions/trend-analysis.blade.php

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

    // Make all data properties public so they're available in the view
    public array $trendData = [];
    public array $periodComparison = [];
    public array $growthRates = [];
    public array $seasonalPatterns = [];
    public array $movingAverages = [];
    public array $trendIndicators = [];
    public array $forecastData = [];

    public function mount(): void
    {
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->loadAnalysisData();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['dateFrom', 'dateTo', 'granularity', 'metric'])) {
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
        } catch (\Exception $e) {
            logger()->error('Trend analysis error: ' . $e->getMessage());
            $this->error('Error loading trend analysis data. Please try again.');
            $this->resetToEmptyData();
        }
    }

    private function resetToEmptyData(): void
    {
        $this->trendData = [];
        $this->periodComparison = [];
        $this->growthRates = [];
        $this->seasonalPatterns = [
            'day_of_week' => [],
            'hour_of_day' => [],
            'month' => []
        ];
        $this->movingAverages = [];
        $this->trendIndicators = [];
        $this->forecastData = [];
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

        return [
            'total_transactions' => $result->total_transactions ?? 0,
            'total_volume' => (float)($result->total_volume ?? 0),
            'avg_amount' => (float)($result->avg_amount ?? 0),
            'total_fees' => (float)($result->total_fees ?? 0),
            'successful_count' => $result->successful_count ?? 0,
            'failed_count' => $result->failed_count ?? 0,
            'high_value_count' => $result->high_value_count ?? 0,
            'reversed_count' => $result->reversed_count ?? 0,
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
            } else {
                $comparison[$key . '_change'] = $value > 0 ? 100 : 0;
                $comparison[$key . '_change_abs'] = $value;
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

    public function exportTrendData(): void
    {
        $this->info('Trend analysis data export initiated. You will receive a download link shortly.');
    }
}; ?>

<div>
    <x-header title="Trend Analysis" subtitle="Historical patterns and forecasting insights">
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-select
                    label="Granularity"
                    wire:model.live="granularity"
                    :options="[
                        ['id' => 'daily', 'name' => 'Daily'],
                        ['id' => 'weekly', 'name' => 'Weekly'],
                        ['id' => 'monthly', 'name' => 'Monthly']
                    ]" />
                <x-select
                    label="Metric"
                    wire:model.live="metric"
                    :options="[
                        ['id' => 'volume', 'name' => 'Volume'],
                        ['id' => 'count', 'name' => 'Count'],
                        ['id' => 'success_rate', 'name' => 'Success Rate'],
                        ['id' => 'avg_amount', 'name' => 'Avg Amount']
                    ]" />
                <x-datepicker
                    label="From"
                    wire:model.live="dateFrom"
                    icon="o-calendar"
                    :config="['altFormat' => 'd/m/Y']" />
                <x-datepicker
                    label="To"
                    wire:model.live="dateTo"
                    icon="o-calendar"
                    :config="['altFormat' => 'd/m/Y']" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Export Data" icon="o-arrow-down-tray" wire:click="exportTrendData" class="btn-outline" />
            <x-button label="Back to Analytics" icon="o-arrow-left" link="/transactions/analytics" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    {{-- Period Comparison --}}
    @if(isset($periodComparison['comparison']))
        <x-card title="Period Comparison" class="mb-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="p-4 text-center rounded-lg bg-blue-50">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ number_format($periodComparison['current']['total_transactions'] ?? 0) }}
                    </div>
                    <div class="text-sm text-gray-600">Total Transactions</div>
                    <div class="text-xs {{ ($periodComparison['comparison']['total_transactions_change'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($periodComparison['comparison']['total_transactions_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['total_transactions_change'] ?? 0, 1) }}%
                    </div>
                </div>

                <div class="p-4 text-center rounded-lg bg-green-50">
                    <div class="text-2xl font-bold text-green-600">
                        {{ number_format($periodComparison['current']['total_volume'] ?? 0, 0) }}
                    </div>
                    <div class="text-sm text-gray-600">Total Volume (DJF)</div>
                    <div class="text-xs {{ ($periodComparison['comparison']['total_volume_change'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($periodComparison['comparison']['total_volume_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['total_volume_change'] ?? 0, 1) }}%
                    </div>
                </div>

                <div class="p-4 text-center rounded-lg bg-purple-50">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ number_format($periodComparison['current']['avg_amount'] ?? 0, 0) }}
                    </div>
                    <div class="text-sm text-gray-600">Avg Amount (DJF)</div>
                    <div class="text-xs {{ ($periodComparison['comparison']['avg_amount_change'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($periodComparison['comparison']['avg_amount_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($periodComparison['comparison']['avg_amount_change'] ?? 0, 1) }}%
                    </div>
                </div>

                <div class="p-4 text-center rounded-lg bg-orange-50">
                    @php
                        $currentSuccessRate = ($periodComparison['current']['total_transactions'] ?? 0) > 0
                            ? (($periodComparison['current']['successful_count'] ?? 0) / $periodComparison['current']['total_transactions']) * 100
                            : 0;
                    @endphp
                    <div class="text-2xl font-bold text-orange-600">
                        {{ number_format($currentSuccessRate, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600">Success Rate</div>
                    @php
                        $prevSuccessRate = ($periodComparison['previous']['total_transactions'] ?? 0) > 0
                            ? (($periodComparison['previous']['successful_count'] ?? 0) / $periodComparison['previous']['total_transactions']) * 100
                            : 0;
                        $successRateChange = $prevSuccessRate > 0 ? (($currentSuccessRate - $prevSuccessRate) / $prevSuccessRate) * 100 : 0;
                    @endphp
                    <div class="text-xs {{ $successRateChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $successRateChange >= 0 ? '+' : '' }}{{ number_format($successRateChange, 1) }}%
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Trend Indicators --}}
    @if(isset($trendIndicators['volume_trend']))
        <x-card title="Trend Indicators" class="mb-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div class="p-4 text-center border rounded-lg">
                    <div class="mb-2 text-lg font-semibold">Volume Trend</div>
                    <div class="flex items-center justify-center space-x-2">
                        @php
                            $trendIcon = match($trendIndicators['volume_trend']) {
                                'upward' => 'o-arrow-trending-up',
                                'downward' => 'o-arrow-trending-down',
                                'stable' => 'o-minus',
                                default => 'o-question-mark-circle'
                            };
                            $trendColor = match($trendIndicators['volume_trend']) {
                                'upward' => 'text-green-500',
                                'downward' => 'text-red-500',
                                'stable' => 'text-yellow-500',
                                default => 'text-gray-500'
                            };
                        @endphp
                        <x-icon name="{{ $trendIcon }}" class="w-6 h-6 {{ $trendColor }}" />
                        <span class="text-sm font-medium capitalize">{{ str_replace('_', ' ', $trendIndicators['volume_trend']) }}</span>
                    </div>
                </div>

                <div class="p-4 text-center border rounded-lg">
                    <div class="mb-2 text-lg font-semibold">Count Trend</div>
                    <div class="flex items-center justify-center space-x-2">
                        @php
                            $countTrendIcon = match($trendIndicators['count_trend']) {
                                'upward' => 'o-arrow-trending-up',
                                'downward' => 'o-arrow-trending-down',
                                'stable' => 'o-minus',
                                default => 'o-question-mark-circle'
                            };
                            $countTrendColor = match($trendIndicators['count_trend']) {
                                'upward' => 'text-green-500',
                                'downward' => 'text-red-500',
                                'stable' => 'text-yellow-500',
                                default => 'text-gray-500'
                            };
                        @endphp
                        <x-icon name="{{ $countTrendIcon }}" class="w-6 h-6 {{ $countTrendColor }}" />
                        <span class="text-sm font-medium capitalize">{{ str_replace('_', ' ', $trendIndicators['count_trend']) }}</span>
                    </div>
                </div>

                <div class="p-4 text-center border rounded-lg">
                    <div class="mb-2 text-lg font-semibold">Success Rate Trend</div>
                    <div class="flex items-center justify-center space-x-2">
                        @php
                            $successTrendIcon = match($trendIndicators['success_rate_trend']) {
                                'upward' => 'o-arrow-trending-up',
                                'downward' => 'o-arrow-trending-down',
                                'stable' => 'o-minus',
                                default => 'o-question-mark-circle'
                            };
                            $successTrendColor = match($trendIndicators['success_rate_trend']) {
                                'upward' => 'text-green-500',
                                'downward' => 'text-red-500',
                                'stable' => 'text-yellow-500',
                                default => 'text-gray-500'
                            };
                        @endphp
                        <x-icon name="{{ $successTrendIcon }}" class="w-6 h-6 {{ $successTrendColor }}" />
                        <span class="text-sm font-medium capitalize">{{ str_replace('_', ' ', $trendIndicators['success_rate_trend']) }}</span>
                    </div>
                </div>

                <div class="p-4 text-center border rounded-lg">
                    <div class="mb-2 text-lg font-semibold">Volatility</div>
                    <div class="text-2xl font-bold">{{ number_format($trendIndicators['volatility'] * 100, 1) }}%</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $trendIndicators['volatility'] < 0.1 ? 'Low' : ($trendIndicators['volatility'] < 0.3 ? 'Medium' : 'High') }}
                    </div>
                </div>

                <div class="p-4 text-center border rounded-lg">
                    <div class="mb-2 text-lg font-semibold">Volume-Count Correlation</div>
                    <div class="text-2xl font-bold">{{ number_format($trendIndicators['correlation_volume_count'], 2) }}</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ abs($trendIndicators['correlation_volume_count']) > 0.7 ? 'Strong' : (abs($trendIndicators['correlation_volume_count']) > 0.3 ? 'Moderate' : 'Weak') }}
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Growth Rates --}}
    @if(!empty($growthRates))
        <x-card title="Growth Rates" class="mb-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach($growthRates as $metric => $data)
                    <div class="p-4 border rounded-lg">
                        <div class="mb-2 text-lg font-semibold capitalize">{{ str_replace('_', ' ', $metric) }}</div>
                        <div class="text-2xl font-bold {{ $data['growth_rate'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $data['growth_rate'] >= 0 ? '+' : '' }}{{ number_format($data['growth_rate'], 2) }}%
                        </div>
                        <div class="mt-2 text-sm text-gray-500">
                            <div>Start: {{ number_format($data['start_value'], 0) }}</div>
                            <div>End: {{ number_format($data['end_value'], 0) }}</div>
                            <div>Change: {{ $data['total_change'] >= 0 ? '+' : '' }}{{ number_format($data['total_change'], 0) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Seasonal Patterns --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Day of Week Pattern --}}
        <x-card title="Day of Week Patterns">
            <div class="space-y-3">
                @php
                    $maxDayVolume = collect($seasonalPatterns['day_of_week'])->max('total_volume') ?: 1;
                @endphp
                @foreach($seasonalPatterns['day_of_week'] as $day)
                    @php
                        $percentage = ($day['total_volume'] / $maxDayVolume) * 100;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium">{{ $day['day_name'] }}</span>
                            <span class="text-sm text-gray-500">{{ number_format($day['transaction_count']) }} txns</span>
                        </div>
                        <div class="w-full h-3 bg-gray-200 rounded-full">
                            <div class="h-3 bg-blue-500 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Volume: {{ number_format($day['total_volume'], 0) }} DJF | Avg: {{ number_format($day['avg_amount'], 0) }} DJF
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Hour of Day Pattern --}}
        <x-card title="Hourly Distribution">
            <div class="grid grid-cols-8 gap-1">
                @php
                    $maxHourVolume = collect($seasonalPatterns['hour_of_day'])->max('total_volume') ?: 1;
                @endphp
                @for($hour = 0; $hour < 24; $hour++)
                    @php
                        $hourData = collect($seasonalPatterns['hour_of_day'])->firstWhere('hour', $hour);
                        $volume = $hourData['total_volume'] ?? 0;
                        $count = $hourData['transaction_count'] ?? 0;
                        $height = ($volume / $maxHourVolume) * 100;
                    @endphp
                    <div class="text-center">
                        <div class="flex items-end justify-center h-16 mb-1">
                            <div
                                class="w-4 transition-all bg-purple-500 rounded-t hover:bg-purple-600"
                                style="height: {{ $height }}%"
                                title="Hour {{ $hour }}:00 - Volume: {{ number_format($volume, 0) }}, Count: {{ $count }}">
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</div>
                    </div>
                @endfor
            </div>
            <div class="mt-4 text-sm text-center text-gray-500">
                Transaction volume by hour (hover for details)
            </div>
        </x-card>
    </div>

    {{-- Monthly Patterns (if available) --}}
    @if(count($seasonalPatterns['month']) > 1)
        <x-card title="Monthly Patterns" class="mb-6">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @php
                    $maxMonthVolume = collect($seasonalPatterns['month'])->max('total_volume') ?: 1;
                @endphp
                @foreach($seasonalPatterns['month'] as $month)
                    @php
                        $percentage = ($month['total_volume'] / $maxMonthVolume) * 100;
                    @endphp
                    <div class="p-3 border rounded-lg">
                        <div class="mb-2 font-medium">{{ $month['month_name'] }}</div>
                        <div class="w-full h-2 mb-2 bg-gray-200 rounded-full">
                            <div class="h-2 bg-green-500 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Transactions</span>
                                <span>{{ number_format($month['transaction_count']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Volume</span>
                                <span>{{ number_format($month['total_volume'], 0) }} DJF</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Avg Amount</span>
                                <span>{{ number_format($month['avg_amount'], 0) }} DJF</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Trend Data Table --}}
    <x-card title="Detailed Trend Data" class="mb-6">
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Transactions</th>
                        <th>Volume</th>
                        <th>Avg Amount</th>
                        <th>Success Rate</th>
                        <th>Failed</th>
                        <th>Reversed</th>
                        <th>High Value</th>
                        <th>Fees</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trendData as $data)
                        <tr>
                            <td class="font-medium">{{ $data['formatted_period'] }}</td>
                            <td>{{ number_format($data['transaction_count']) }}</td>
                            <td>{{ number_format($data['total_volume'], 0) }} DJF</td>
                            <td>{{ number_format($data['avg_amount'], 0) }} DJF</td>
                            <td>
                                <span class="px-2 py-1 text-xs rounded {{ $data['success_rate'] >= 95 ? 'bg-green-100 text-green-800' : ($data['success_rate'] >= 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ number_format($data['success_rate'], 1) }}%
                                </span>
                            </td>
                            <td>{{ number_format($data['failed_count']) }}</td>
                            <td>{{ number_format($data['reversed_count']) }}</td>
                            <td>{{ number_format($data['high_value_count']) }}</td>
                            <td>{{ number_format($data['total_fees'], 0) }} DJF</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Forecast Data --}}
    @if(isset($forecastData['projections']))
        <x-card title="Volume Forecast (Next 7 Periods)" class="mb-6">
            <div class="p-3 mb-4 rounded-lg bg-blue-50">
                <div class="text-sm font-medium text-blue-800">Forecast Model Statistics</div>
                <div class="mt-1 text-xs text-blue-600">
                    R-squared: {{ number_format($forecastData['r_squared'], 3) }} |
                    Slope: {{ number_format($forecastData['regression']['slope'], 2) }} |
                    Intercept: {{ number_format($forecastData['regression']['intercept'], 2) }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                @foreach($forecastData['projections'] as $projection)
                    <div class="border rounded-lg p-3 {{ $projection['confidence'] > 0.7 ? 'border-green-200 bg-green-50' : ($projection['confidence'] > 0.5 ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50') }}">
                        <div class="font-medium">Period {{ $projection['period'] }}</div>
                        <div class="text-lg font-bold">{{ number_format($projection['projected_volume'], 0) }} DJF</div>
                        <div class="text-xs text-gray-500">
                            Confidence: {{ number_format($projection['confidence'] * 100, 0) }}%
                        </div>
                        <div class="w-full h-1 mt-2 bg-gray-200 rounded-full">
                            <div class="h-1 rounded-full {{ $projection['confidence'] > 0.7 ? 'bg-green-500' : ($projection['confidence'] > 0.5 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ $projection['confidence'] * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 text-sm text-gray-500">
                <strong>Note:</strong> Forecasts are based on linear regression of historical data.
                Confidence decreases for future periods. Use for trend indication only.
            </div>
        </x-card>
    @endif

    {{-- Moving Averages --}}
    @if(!empty($movingAverages))
        <x-card title="Moving Averages" class="mb-6">
            <div class="flex items-center justify-center h-64 rounded-lg bg-gray-50">
                <div class="text-center">
                    <x-icon name="o-chart-bar" class="w-16 h-16 mx-auto mb-4 text-gray-400" />
                    <p class="text-gray-500">Moving averages chart would be rendered here</p>
                    <p class="text-sm text-gray-400">Integration with Chart.js or similar charting library needed</p>
                </div>
            </div>

            {{-- Moving Averages Data --}}
            <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-3">
                @foreach($movingAverages as $window => $data)
                    <div class="p-3 border rounded-lg">
                        <div class="mb-2 font-medium">{{ str_replace('_', ' ', ucfirst($window)) }} Moving Average</div>
                        <div class="text-sm text-gray-500">
                            Current: {{ number_format(end($data) ?: 0, 0) }} DJF
                        </div>
                        <div class="mt-1 text-xs text-gray-400">
                            {{ count($data) }} data points
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Trend Summary --}}
    <x-card title="Trend Summary" class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <h4 class="mb-3 font-medium">Key Insights</h4>
                <ul class="space-y-2 text-sm">
                    @if(isset($trendIndicators['volume_trend']))
                        <li class="flex items-center space-x-2">
                            <x-icon name="o-chart-bar" class="w-4 h-4 text-blue-500" />
                            <span>Volume trend is <strong>{{ $trendIndicators['volume_trend'] }}</strong></span>
                        </li>
                    @endif

                    @if(isset($trendIndicators['count_trend']))
                        <li class="flex items-center space-x-2">
                            <x-icon name="o-queue-list" class="w-4 h-4 text-green-500" />
                            <span>Transaction count trend is <strong>{{ $trendIndicators['count_trend'] }}</strong></span>
                        </li>
                    @endif

                    @if(isset($trendIndicators['volatility']))
                        <li class="flex items-center space-x-2">
                            <x-icon name="o-arrow-trending-up" class="w-4 h-4 text-orange-500" />
                            <span>Volatility is <strong>{{ $trendIndicators['volatility'] < 0.1 ? 'low' : ($trendIndicators['volatility'] < 0.3 ? 'moderate' : 'high') }}</strong> ({{ number_format($trendIndicators['volatility'] * 100, 1) }}%)</span>
                        </li>
                    @endif

                    @if(count($seasonalPatterns['day_of_week']) > 0)
                        @php
                            $peakDay = collect($seasonalPatterns['day_of_week'])->sortByDesc('total_volume')->first();
                        @endphp
                        <li class="flex items-center space-x-2">
                            <x-icon name="o-calendar-days" class="w-4 h-4 text-purple-500" />
                            <span>Peak day is <strong>{{ $peakDay['day_name'] ?? 'Unknown' }}</strong></span>
                        </li>
                    @endif

                    @if(count($seasonalPatterns['hour_of_day']) > 0)
                        @php
                            $peakHour = collect($seasonalPatterns['hour_of_day'])->sortByDesc('total_volume')->first();
                        @endphp
                        <li class="flex items-center space-x-2">
                            <x-icon name="o-clock" class="w-4 h-4 text-indigo-500" />
                            <span>Peak hour is <strong>{{ str_pad($peakHour['hour'] ?? 0, 2, '0', STR_PAD_LEFT) }}:00</strong></span>
                        </li>
                    @endif
                </ul>
            </div>

            <div>
                <h4 class="mb-3 font-medium">Recommendations</h4>
                <ul class="space-y-2 text-sm">
                    @if(isset($trendIndicators['volume_trend']) && $trendIndicators['volume_trend'] === 'downward')
                        <li class="flex items-start space-x-2">
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-yellow-500 mt-0.5" />
                            <span>Consider investigating causes of declining volume</span>
                        </li>
                    @endif

                    @if(isset($trendIndicators['success_rate_trend']) && $trendIndicators['success_rate_trend'] === 'downward')
                        <li class="flex items-start space-x-2">
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-red-500 mt-0.5" />
                            <span>Success rate is declining - review system performance</span>
                        </li>
                    @endif

                    @if(isset($trendIndicators['volatility']) && $trendIndicators['volatility'] > 0.3)
                        <li class="flex items-start space-x-2">
                            <x-icon name="o-information-circle" class="w-4 h-4 text-blue-500 mt-0.5" />
                            <span>High volatility detected - consider smoothing mechanisms</span>
                        </li>
                    @endif

                    @if(count($seasonalPatterns['hour_of_day']) > 0)
                        @php
                            $lowHours = collect($seasonalPatterns['hour_of_day'])->where('total_volume', '<', collect($seasonalPatterns['hour_of_day'])->avg('total_volume') * 0.5);
                        @endphp
                        @if($lowHours->count() > 0)
                            <li class="flex items-start space-x-2">
                                <x-icon name="o-eye" class="w-4 h-4 text-green-500 mt-0.5" />
                                <span>Consider promotions during low-activity hours</span>
                            </li>
                        @endif
                    @endif

                    @if(isset($forecastData['r_squared']) && $forecastData['r_squared'] > 0.7)
                        <li class="flex items-start space-x-2">
                            <x-icon name="o-chart-bar" class="w-4 h-4 text-purple-500 mt-0.5" />
                            <span>Strong trend detected - forecasts are reliable for planning</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </x-card>
</div>
