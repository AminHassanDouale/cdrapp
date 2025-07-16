<?php
// resources/views/livewire/transactions/volume-analysis.blade.php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $groupBy = 'daily'; // daily, weekly, monthly
    public string $currency = 'all';
    public string $viewType = 'volume'; // volume, count, both

    public function mount(): void
    {
        // Set very conservative memory and time limits
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', '120');

        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subDays(7)->format('Y-m-d'); // Start with just 7 days
    }

    public function with(): array
    {
        $startDate = Carbon::parse($this->dateFrom);
        $endDate = Carbon::parse($this->dateTo);

        // Very strict date range limits
        $daysDiff = $startDate->diffInDays($endDate);
        if ($daysDiff > 90) {
            $this->addError('dateFrom', 'Maximum date range is 90 days. Please select a smaller range.');
            return $this->getEmptyData();
        }

        // Create cache key for expensive operations
        $cacheKey = "volume_analysis_{$this->dateFrom}_{$this->dateTo}_{$this->currency}_{$this->groupBy}";

        try {
            // Try to get from cache first (5 minute cache)
            $data = Cache::remember($cacheKey, 300, function() use ($startDate, $endDate) {
                return $this->loadVolumeData($startDate, $endDate);
            });

            return $data;
        } catch (\Exception $e) {
            logger()->error('Volume analysis error: ' . $e->getMessage());
            $this->addError('general', 'Error loading data. Please try a smaller date range or contact support.');
            return $this->getEmptyData();
        }
    }

    private function loadVolumeData($startDate, $endDate): array
    {
        // Load data with minimal memory footprint
        return [
            'volumeTrends' => $this->getVolumeTrendsOptimized($startDate, $endDate),
            'volumeByCurrency' => $this->getVolumeByCurrencyOptimized($startDate, $endDate),
            'volumeByType' => $this->getVolumeByTransactionTypeOptimized($startDate, $endDate),
            'volumeByHour' => $this->getVolumeByHourOptimized($startDate, $endDate),
            'volumeStats' => $this->getVolumeStatisticsOptimized($startDate, $endDate),
            'topVolumeDays' => $this->getTopVolumeDaysOptimized($startDate, $endDate),
            'volumeDistribution' => $this->getVolumeDistributionOptimized($startDate, $endDate),
            'currencies' => $this->getAvailableCurrencies(),
        ];
    }

    private function getVolumeTrendsOptimized($startDate, $endDate): array
    {
        // Use raw SQL for maximum efficiency
        $sql = "
            SELECT
                TO_CHAR(trans_initate_time, 'YYYY-MM-DD') as period,
                TO_CHAR(trans_initate_time, 'YYYY-MM-DD') as formatted_period,
                COUNT(*) as transaction_count,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount,
                SUM(COALESCE(fee, 0)) as total_fees,
                SUM(CASE WHEN trans_status = 'Completed' THEN actual_amount ELSE 0 END) as successful_volume,
                COUNT(CASE WHEN trans_status = 'Completed' THEN 1 END) as successful_count
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            " . ($this->currency !== 'all' ? "AND currency = ?" : "") . "
            GROUP BY TO_CHAR(trans_initate_time, 'YYYY-MM-DD')
            ORDER BY TO_CHAR(trans_initate_time, 'YYYY-MM-DD')
            LIMIT 100
        ";

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $results = DB::select($sql, $bindings);

        return array_map(function($row) {
            return [
                'period' => $row->period,
                'formatted_period' => $row->formatted_period,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
                'total_fees' => (float)$row->total_fees,
                'successful_volume' => (float)$row->successful_volume,
                'successful_count' => (int)$row->successful_count,
            ];
        }, $results);
    }

    private function getVolumeByCurrencyOptimized($startDate, $endDate): array
    {
        $sql = "
            SELECT
                currency,
                COUNT(*) as transaction_count,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount,
                MIN(actual_amount) as min_amount,
                MAX(actual_amount) as max_amount,
                SUM(COALESCE(fee, 0)) as total_fees
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            GROUP BY currency
            ORDER BY SUM(actual_amount) DESC
            LIMIT 10
        ";

        $results = DB::select($sql, [$startDate, $endDate]);

        return array_map(function($row) {
            return [
                'currency' => $row->currency,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
                'min_amount' => (float)$row->min_amount,
                'max_amount' => (float)$row->max_amount,
                'total_fees' => (float)$row->total_fees,
            ];
        }, $results);
    }

    private function getVolumeByTransactionTypeOptimized($startDate, $endDate): array
    {
        // Get aggregated data first
        $sql = "
            SELECT
                td.tranactiontype,
                COUNT(*) as transaction_count,
                SUM(t.actual_amount) as total_volume,
                AVG(t.actual_amount) as avg_amount,
                SUM(COALESCE(t.fee, 0)) as total_fees,
                SUM(CASE WHEN t.trans_status = 'Completed' THEN t.actual_amount ELSE 0 END) as successful_volume
            FROM lbi_ods.t_o_trans_record t
            INNER JOIN lbi_ods.t_o_orderhis td ON t.orderid = td.orderid
            WHERE t.trans_initate_time BETWEEN ? AND ?
            " . ($this->currency !== 'all' ? "AND t.currency = ?" : "") . "
            AND td.tranactiontype IS NOT NULL
            GROUP BY td.tranactiontype
            ORDER BY SUM(t.actual_amount) DESC
            LIMIT 10
        ";

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $results = DB::select($sql, $bindings);

        if (empty($results)) {
            return [];
        }

        // Get transaction type names in one query
        $typeIds = array_column($results, 'tranactiontype');
        $typeNames = DB::table('lbi_ods.t_o_transaction_type')
            ->whereIn('txn_index', $typeIds)
            ->get(['txn_index', 'txn_type_name', 'alias'])
            ->keyBy('txn_index');

        return array_map(function($row) use ($typeNames) {
            $typeInfo = $typeNames->get($row->tranactiontype);
            return [
                'txn_type_name' => $typeInfo->txn_type_name ?? 'Unknown Type',
                'alias' => $typeInfo->alias ?? null,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
                'total_fees' => (float)$row->total_fees,
                'successful_volume' => (float)$row->successful_volume,
            ];
        }, $results);
    }

    private function getVolumeByHourOptimized($startDate, $endDate): array
    {
        $sql = "
            SELECT
                EXTRACT(HOUR FROM trans_initate_time) as hour,
                COUNT(*) as transaction_count,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            " . ($this->currency !== 'all' ? "AND currency = ?" : "") . "
            GROUP BY EXTRACT(HOUR FROM trans_initate_time)
            ORDER BY EXTRACT(HOUR FROM trans_initate_time)
        ";

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $results = DB::select($sql, $bindings);

        return array_map(function($row) {
            return [
                'hour' => (int)$row->hour,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
            ];
        }, $results);
    }

    private function getVolumeStatisticsOptimized($startDate, $endDate): array
    {
        // Use PostgreSQL's built-in statistical functions
        $sql = "
            SELECT
                COUNT(*) as total_transactions,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount,
                MIN(actual_amount) as min_amount,
                MAX(actual_amount) as max_amount,
                STDDEV(actual_amount) as std_dev,
                SUM(COALESCE(fee, 0)) as total_fees,
                SUM(CASE WHEN trans_status = 'Completed' THEN actual_amount ELSE 0 END) as successful_volume,
                COUNT(CASE WHEN trans_status = 'Completed' THEN 1 END) as successful_transactions,
                PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY actual_amount) as p25,
                PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY actual_amount) as p50,
                PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY actual_amount) as p75,
                PERCENTILE_CONT(0.90) WITHIN GROUP (ORDER BY actual_amount) as p90,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY actual_amount) as p95,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY actual_amount) as p99
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            " . ($this->currency !== 'all' ? "AND currency = ?" : "");

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $result = DB::select($sql, $bindings)[0] ?? null;

        if (!$result) {
            return [
                'total_transactions' => 0, 'total_volume' => 0, 'avg_amount' => 0,
                'min_amount' => 0, 'max_amount' => 0, 'std_dev' => 0, 'total_fees' => 0,
                'successful_volume' => 0, 'successful_transactions' => 0,
                'p25' => 0, 'p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0
            ];
        }

        return [
            'total_transactions' => (int)$result->total_transactions,
            'total_volume' => (float)$result->total_volume,
            'avg_amount' => (float)$result->avg_amount,
            'min_amount' => (float)$result->min_amount,
            'max_amount' => (float)$result->max_amount,
            'std_dev' => (float)$result->std_dev,
            'total_fees' => (float)$result->total_fees,
            'successful_volume' => (float)$result->successful_volume,
            'successful_transactions' => (int)$result->successful_transactions,
            'p25' => round((float)$result->p25, 2),
            'p50' => round((float)$result->p50, 2),
            'p75' => round((float)$result->p75, 2),
            'p90' => round((float)$result->p90, 2),
            'p95' => round((float)$result->p95, 2),
            'p99' => round((float)$result->p99, 2),
        ];
    }

    private function getTopVolumeDaysOptimized($startDate, $endDate): array
    {
        $sql = "
            SELECT
                trans_initate_time::date as date,
                COUNT(*) as transaction_count,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount,
                TO_CHAR(trans_initate_time, 'Day') as day_name
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            " . ($this->currency !== 'all' ? "AND currency = ?" : "") . "
            GROUP BY trans_initate_time::date, TO_CHAR(trans_initate_time, 'Day')
            ORDER BY SUM(actual_amount) DESC
            LIMIT 10
        ";

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $results = DB::select($sql, $bindings);

        return array_map(function($row) {
            return [
                'date' => $row->date,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
                'day_name' => trim($row->day_name),
            ];
        }, $results);
    }

    private function getVolumeDistributionOptimized($startDate, $endDate): array
    {
        $sql = "
            SELECT
                CASE
                    WHEN actual_amount < 100 THEN '0-100'
                    WHEN actual_amount < 500 THEN '100-500'
                    WHEN actual_amount < 1000 THEN '500-1K'
                    WHEN actual_amount < 5000 THEN '1K-5K'
                    WHEN actual_amount < 10000 THEN '5K-10K'
                    WHEN actual_amount < 50000 THEN '10K-50K'
                    WHEN actual_amount < 100000 THEN '50K-100K'
                    ELSE '100K+'
                END as amount_range,
                COUNT(*) as transaction_count,
                SUM(actual_amount) as total_volume,
                AVG(actual_amount) as avg_amount
            FROM lbi_ods.t_o_trans_record
            WHERE trans_initate_time BETWEEN ? AND ?
            AND trans_status = 'Completed'
            " . ($this->currency !== 'all' ? "AND currency = ?" : "") . "
            GROUP BY
                CASE
                    WHEN actual_amount < 100 THEN '0-100'
                    WHEN actual_amount < 500 THEN '100-500'
                    WHEN actual_amount < 1000 THEN '500-1K'
                    WHEN actual_amount < 5000 THEN '1K-5K'
                    WHEN actual_amount < 10000 THEN '5K-10K'
                    WHEN actual_amount < 50000 THEN '10K-50K'
                    WHEN actual_amount < 100000 THEN '50K-100K'
                    ELSE '100K+'
                END
            ORDER BY MIN(actual_amount)
        ";

        $bindings = [$startDate, $endDate];
        if ($this->currency !== 'all') {
            $bindings[] = $this->currency;
        }

        $results = DB::select($sql, $bindings);

        return array_map(function($row) {
            return [
                'amount_range' => $row->amount_range,
                'transaction_count' => (int)$row->transaction_count,
                'total_volume' => (float)$row->total_volume,
                'avg_amount' => (float)$row->avg_amount,
            ];
        }, $results);
    }

    private function getAvailableCurrencies(): array
    {
        // Use simple query with limit
        $currencies = DB::table('lbi_ods.t_o_trans_record')
            ->distinct()
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->orderBy('currency')
            ->limit(20)
            ->pluck('currency')
            ->prepend('all')
            ->map(fn($curr) => ['id' => $curr, 'name' => $curr === 'all' ? 'All Currencies' : $curr])
            ->toArray();

        return $currencies;
    }

    private function getEmptyData(): array
    {
        return [
            'volumeTrends' => [],
            'volumeByCurrency' => [],
            'volumeByType' => [],
            'volumeByHour' => [],
            'volumeStats' => [
                'total_volume' => 0, 'total_transactions' => 0, 'avg_amount' => 0, 'std_dev' => 0,
                'p25' => 0, 'p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0,
                'min_amount' => 0, 'max_amount' => 0, 'total_fees' => 0,
                'successful_volume' => 0, 'successful_transactions' => 0
            ],
            'topVolumeDays' => [],
            'volumeDistribution' => [],
            'currencies' => [['id' => 'all', 'name' => 'All Currencies']],
        ];
    }

    public function exportVolumeData(): void
    {
        $this->info('Volume analysis data export initiated. You will receive a download link shortly.');
    }
}; ?>

<div>
    <x-header title="Volume Analysis" subtitle="Detailed transaction volume analysis and trends">
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-select
                    label="Group By"
                    wire:model.live="groupBy"
                    :options="[
                        ['id' => 'daily', 'name' => 'Daily'],
                        ['id' => 'weekly', 'name' => 'Weekly'],
                        ['id' => 'monthly', 'name' => 'Monthly']
                    ]" />
                <x-select
                    label="Currency"
                    wire:model.live="currency"
                    :options="$currencies" />
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
            <x-button label="Export Data" icon="o-arrow-down-tray" wire:click="exportVolumeData" class="btn-outline" />
            <x-button label="Back to Analytics" icon="o-arrow-left" link="/transactions/analytics" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    {{-- Show any errors --}}
    @if ($errors->any())
        <div class="mb-4">
            @foreach ($errors->all() as $error)
                <x-alert icon="o-exclamation-triangle" class="mb-2 alert-warning">
                    {{ $error }}
                </x-alert>
            @endforeach
        </div>
    @endif

    {{-- Volume Statistics Overview --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
        <x-stat
            title="Total Volume"
            :value="number_format($volumeStats['total_volume'] ?? 0, 0) . ' ' . ($currency === 'all' ? 'DJF' : $currency)"
            icon="o-banknotes"
            color="text-green-500"
            :description="'From ' . number_format($volumeStats['total_transactions'] ?? 0) . ' transactions'" />

        <x-stat
            title="Average Amount"
            :value="number_format($volumeStats['avg_amount'] ?? 0, 0) . ' ' . ($currency === 'all' ? 'DJF' : $currency)"
            icon="o-calculator"
            color="text-blue-500"
            :description="'Std Dev: ' . number_format($volumeStats['std_dev'] ?? 0, 0)" />

        <x-stat
            title="Median (P50)"
            :value="number_format($volumeStats['p50'] ?? 0, 0) . ' ' . ($currency === 'all' ? 'DJF' : $currency)"
            icon="o-chart-bar"
            color="text-purple-500"
            :description="'P75: ' . number_format($volumeStats['p75'] ?? 0, 0)" />

        <x-stat
            title="Maximum Amount"
            :value="number_format($volumeStats['max_amount'] ?? 0, 0) . ' ' . ($currency === 'all' ? 'DJF' : $currency)"
            icon="o-arrow-trending-up"
            color="text-orange-500"
            :description="'P99: ' . number_format($volumeStats['p99'] ?? 0, 0)" />
    </div>

    {{-- Volume Trends Chart Area --}}
    <x-card title="Volume Trends" class="mb-6">
        <div class="flex items-center justify-center rounded-lg h-80 bg-gray-50">
            <div class="text-center">
                <x-icon name="o-chart-bar" class="w-16 h-16 mx-auto mb-4 text-gray-400" />
                <p class="text-gray-500">Volume trends chart would be rendered here</p>
                <p class="text-sm text-gray-400">Integration with Chart.js or similar charting library needed</p>
                <div class="mt-4 text-sm text-blue-600">
                    Limited to 90 days for optimal performance
                </div>
            </div>
        </div>

        {{-- Trends Data Table --}}
        <div class="mt-6 overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Transactions</th>
                        <th>Total Volume</th>
                        <th>Avg Amount</th>
                        <th>Total Fees</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($volumeTrends as $trend)
                        @php
                            $successRate = $trend['transaction_count'] > 0 ? ($trend['successful_count'] / $trend['transaction_count']) * 100 : 0;
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $trend['formatted_period'] }}</td>
                            <td>{{ number_format($trend['transaction_count']) }}</td>
                            <td>{{ number_format($trend['total_volume'], 0) }} {{ $currency === 'all' ? 'DJF' : $currency }}</td>
                            <td>{{ number_format($trend['avg_amount'], 0) }} {{ $currency === 'all' ? 'DJF' : $currency }}</td>
                            <td>{{ number_format($trend['total_fees'], 0) }} {{ $currency === 'all' ? 'DJF' : $currency }}</td>
                            <td>
                                <span class="px-2 py-1 text-xs rounded {{ $successRate >= 95 ? 'bg-green-100 text-green-800' : ($successRate >= 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ number_format($successRate, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">
                                No data available for the selected period
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Rest of the template remains the same but with added empty state handling --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Volume Distribution by Amount Range --}}
        <x-card title="Volume Distribution by Amount Range">
            @if(count($volumeDistribution) > 0)
                <div class="space-y-3">
                    @php
                        $totalVolume = collect($volumeDistribution)->sum('total_volume');
                        $totalTransactions = collect($volumeDistribution)->sum('transaction_count');
                    @endphp
                    @foreach($volumeDistribution as $range)
                        @php
                            $volumePercentage = $totalVolume > 0 ? ($range['total_volume'] / $totalVolume) * 100 : 0;
                            $countPercentage = $totalTransactions > 0 ? ($range['transaction_count'] / $totalTransactions) * 100 : 0;
                        @endphp
                        <div class="p-3 border rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium">{{ $range['amount_range'] }} {{ $currency === 'all' ? 'DJF' : $currency }}</span>
                                <span class="text-sm text-gray-500">{{ number_format($range['transaction_count']) }} txns</span>
                            </div>
                            <div class="w-full h-2 mb-2 bg-gray-200 rounded-full">
                                <div class="h-2 bg-blue-500 rounded-full" style="width: {{ $volumePercentage }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Volume: {{ number_format($range['total_volume'], 0) }} ({{ number_format($volumePercentage, 1) }}%)</span>
                                <span>Count: {{ number_format($countPercentage, 1) }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-gray-500">
                    No volume distribution data available
                </div>
            @endif
        </x-card>

        {{-- Performance Notice --}}
        <x-card title="Performance Notice">
            <div class="space-y-4">
                <div class="p-4 rounded-lg bg-blue-50">
                    <h4 class="mb-2 font-medium text-blue-900">Optimized for Large Datasets</h4>
                    <ul class="space-y-1 text-sm text-blue-800">
                        <li>• Maximum 90-day date range</li>
                        <li>• Results cached for 5 minutes</li>
                        <li>• Limited to top 10 results per category</li>
                        <li>• Uses PostgreSQL built-in analytics functions</li>
                    </ul>
                </div>

                <div class="p-4 rounded-lg bg-yellow-50">
                    <h4 class="mb-2 font-medium text-yellow-900">For Larger Analysis</h4>
                    <p class="text-sm text-yellow-800">
                        For analysis beyond 90 days or more detailed breakdowns,
                        consider using the export function or running reports during off-peak hours.
                    </p>
                </div>
            </div>
        </x-card>
    </div>
</div>
