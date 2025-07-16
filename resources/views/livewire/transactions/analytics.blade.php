<?php
// resources/views/livewire/transactions/analytics.blade.php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionType;
use App\Models\ReasonType;
use App\Models\AccountType;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $period = '30'; // days

    public function mount(): void
    {
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
    }

    public function with(): array
    {
        $startDate = Carbon::parse($this->dateFrom);
        $endDate = Carbon::parse($this->dateTo);

        // Overview Statistics
        $overviewStats = $this->getOverviewStats($startDate, $endDate);

        // Transaction Volume by Status
        $statusBreakdown = $this->getStatusBreakdown($startDate, $endDate);

        // Daily Transaction Trends
        $dailyTrends = $this->getDailyTrends($startDate, $endDate);

        // Top Transaction Types
        $topTransactionTypes = $this->getTopTransactionTypes($startDate, $endDate);

        // Currency Distribution
        $currencyDistribution = $this->getCurrencyDistribution($startDate, $endDate);

        // Channel Performance
        $channelPerformance = $this->getChannelPerformance($startDate, $endDate);

        // High Value Transactions
        $highValueTransactions = $this->getHighValueTransactions($startDate, $endDate);

        // Performance Metrics
        $performanceMetrics = $this->getPerformanceMetrics($startDate, $endDate);

        return [
            'overviewStats' => $overviewStats,
            'statusBreakdown' => $statusBreakdown,
            'dailyTrends' => $dailyTrends,
            'topTransactionTypes' => $topTransactionTypes,
            'currencyDistribution' => $currencyDistribution,
            'channelPerformance' => $channelPerformance,
            'highValueTransactions' => $highValueTransactions,
            'performanceMetrics' => $performanceMetrics,
        ];
    }

    private function getOverviewStats($startDate, $endDate): array
    {
        $baseQuery = Transaction::whereBetween('trans_initate_time', [$startDate, $endDate]);

        $totalTransactions = $baseQuery->count();
        $totalVolume = $baseQuery->sum('actual_amount');
        $totalFees = $baseQuery->sum('fee');
        $successfulTransactions = $baseQuery->where('trans_status', 'Completed')->count();
        $failedTransactions = $baseQuery->where('trans_status', 'Failed')->count();
        $pendingTransactions = $baseQuery->whereIn('trans_status', ['Pending', 'Pending Authorized'])->count();
        $reversedTransactions = $baseQuery->where('is_reversed', 1)->count();

        $successRate = $totalTransactions > 0 ? ($successfulTransactions / $totalTransactions) * 100 : 0;
        $averageAmount = $totalTransactions > 0 ? $totalVolume / $totalTransactions : 0;

        // Previous period comparison
        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate));
        $previousEndDate = $startDate->copy()->subDay();
        $previousTotalTransactions = Transaction::whereBetween('trans_initate_time', [$previousStartDate, $previousEndDate])->count();
        $previousTotalVolume = Transaction::whereBetween('trans_initate_time', [$previousStartDate, $previousEndDate])->sum('actual_amount');

        $transactionGrowth = $previousTotalTransactions > 0 ? (($totalTransactions - $previousTotalTransactions) / $previousTotalTransactions) * 100 : 0;
        $volumeGrowth = $previousTotalVolume > 0 ? (($totalVolume - $previousTotalVolume) / $previousTotalVolume) * 100 : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_volume' => $totalVolume,
            'total_fees' => $totalFees,
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
            'pending_transactions' => $pendingTransactions,
            'reversed_transactions' => $reversedTransactions,
            'success_rate' => round($successRate, 2),
            'average_amount' => round($averageAmount, 2),
            'transaction_growth' => round($transactionGrowth, 2),
            'volume_growth' => round($volumeGrowth, 2),
        ];
    }

    private function getStatusBreakdown($startDate, $endDate): array
    {
        return Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select('trans_status', DB::raw('count(*) as count'), DB::raw('sum(actual_amount) as volume'))
            ->groupBy('trans_status')
            ->orderByDesc('count')
            ->get()
            ->map(function($item) {
                return [
                    'trans_status' => $item->trans_status,
                    'count' => $item->count,
                    'volume' => $item->volume,
                ];
            })
            ->toArray();
    }

    private function getDailyTrends($startDate, $endDate): array
    {
        return Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(trans_initate_time) as date'),
                DB::raw('count(*) as transactions'),
                DB::raw('sum(actual_amount) as volume'),
                DB::raw('sum(case when trans_status = \'Completed\' then 1 else 0 end) as successful'),
                DB::raw('sum(case when trans_status = \'Failed\' then 1 else 0 end) as failed')
            )
            ->groupBy(DB::raw('DATE(trans_initate_time)'))
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'transactions' => $item->transactions,
                    'volume' => $item->volume,
                    'successful' => $item->successful,
                    'failed' => $item->failed,
                ];
            })
            ->toArray();
    }

    private function getTopTransactionTypes($startDate, $endDate): array
    {
        // Use alternative approach to avoid join issues
        return DB::table('lbi_ods.t_o_trans_record as t')
            ->join('lbi_ods.t_o_orderhis as td', 't.orderid', '=', 'td.orderid')
            ->whereBetween('t.trans_initate_time', [$startDate, $endDate])
            ->whereNotNull('td.tranactiontype')
            ->select(
                'td.tranactiontype',
                DB::raw('count(*) as transaction_count'),
                DB::raw('sum(t.actual_amount) as total_volume'),
                DB::raw('avg(t.actual_amount) as avg_amount')
            )
            ->groupBy('td.tranactiontype')
            ->orderByDesc('transaction_count')
            ->limit(10)
            ->get()
            ->map(function($item) {
                // Get transaction type details separately to avoid join issues
                $transactionType = DB::table('lbi_ods.t_o_transaction_type')
                    ->where('txn_index', $item->tranactiontype)
                    ->first();

                return [
                    'txn_type_name' => $transactionType->txn_type_name ?? 'Unknown Type',
                    'alias' => $transactionType->alias ?? null,
                    'transaction_count' => $item->transaction_count,
                    'total_volume' => $item->total_volume,
                    'avg_amount' => $item->avg_amount,
                ];
            })
            ->toArray();
    }

    private function getCurrencyDistribution($startDate, $endDate): array
    {
        return Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->select('currency', DB::raw('count(*) as count'), DB::raw('sum(actual_amount) as volume'))
            ->groupBy('currency')
            ->orderByDesc('volume')
            ->get()
            ->map(function($item) {
                return [
                    'currency' => $item->currency,
                    'count' => $item->count,
                    'volume' => $item->volume,
                ];
            })
            ->toArray();
    }

    private function getChannelPerformance($startDate, $endDate): array
    {
        return DB::table('lbi_ods.t_o_trans_record as t')
            ->join('lbi_ods.t_o_orderhis as td', 't.orderid', '=', 'td.orderid')
            ->whereBetween('t.trans_initate_time', [$startDate, $endDate])
            ->select(
                'td.channel',
                DB::raw('count(*) as transaction_count'),
                DB::raw('sum(t.actual_amount) as total_volume'),
                DB::raw('sum(case when t.trans_status = \'Completed\' then 1 else 0 end) as successful_count'),
                DB::raw('avg(case when td.endtime is not null and td.createtime is not null then EXTRACT(EPOCH FROM (td.endtime::timestamp - td.createtime::timestamp)) end) as avg_processing_time')
            )
            ->groupBy('td.channel')
            ->orderByDesc('transaction_count')
            ->get()
            ->map(function($item) {
                $successRate = $item->transaction_count > 0 ? ($item->successful_count / $item->transaction_count) * 100 : 0;

                return [
                    'channel' => $item->channel,
                    'transaction_count' => $item->transaction_count,
                    'total_volume' => $item->total_volume,
                    'successful_count' => $item->successful_count,
                    'avg_processing_time' => $item->avg_processing_time,
                    'success_rate' => round($successRate, 2),
                ];
            })
            ->toArray();
    }

    private function getHighValueTransactions($startDate, $endDate): array
    {
        return Transaction::whereBetween('trans_initate_time', [$startDate, $endDate])
            ->where('actual_amount', '>=', 10000) // High value threshold
            ->with(['transactionDetails.transactionType', 'reasonType'])
            ->orderByDesc('actual_amount')
            ->limit(10)
            ->get()
            ->map(function($transaction) {
                return [
                    'orderid' => $transaction->orderid,
                    'actual_amount' => $transaction->actual_amount,
                    'currency' => $transaction->currency,
                    'trans_status' => $transaction->trans_status,
                    'trans_initate_time' => $transaction->trans_initate_time,
                    'debit_party_mnemonic' => $transaction->debit_party_mnemonic,
                    'credit_party_mnemonic' => $transaction->credit_party_mnemonic,
                    'status_color' => $transaction->status_color,
                    'transaction_details' => $transaction->transactionDetails ? [
                        'transaction_type' => $transaction->transactionDetails->transactionType ? [
                            'display_name' => $transaction->transactionDetails->transactionType->display_name
                        ] : null
                    ] : null,
                ];
            })
            ->toArray();
    }

    private function getPerformanceMetrics($startDate, $endDate): array
    {
        $avgProcessingTime = DB::table('lbi_ods.t_o_orderhis')
            ->whereBetween('createtime', [$startDate, $endDate])
            ->whereNotNull('endtime')
            ->whereNotNull('createtime')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (endtime::timestamp - createtime::timestamp))) as avg_time')
            ->value('avg_time');

        $peakHour = DB::table('lbi_ods.t_o_trans_record')
            ->whereBetween('trans_initate_time', [$startDate, $endDate])
            ->selectRaw('EXTRACT(HOUR FROM trans_initate_time) as hour, count(*) as count')
            ->groupBy(DB::raw('EXTRACT(HOUR FROM trans_initate_time)'))
            ->orderByDesc('count')
            ->first();

        $errorRate = DB::table('lbi_ods.t_o_orderhis as td')
            ->join('lbi_ods.t_o_trans_record as t', 'td.orderid', '=', 't.orderid')
            ->whereBetween('t.trans_initate_time', [$startDate, $endDate])
            ->selectRaw('
                count(*) as total,
                sum(case when td.errorcode is not null and td.errorcode != \'\' and td.errorcode != \'NULL\' then 1 else 0 end) as errors
            ')
            ->first();

        return [
            'avg_processing_time' => round($avgProcessingTime ?? 0, 2),
            'peak_hour' => $peakHour->hour ?? 0,
            'peak_hour_count' => $peakHour->count ?? 0,
            'error_rate' => $errorRate && $errorRate->total > 0 ? round(($errorRate->errors / $errorRate->total) * 100, 2) : 0,
        ];
    }

    public function refreshData(): void
    {
        $this->success('Analytics data refreshed successfully.');
    }

    public function exportReport(): void
    {
        $this->info('Analytics report export initiated. You will receive a download link shortly.');
    }
}; ?>

<div>
    <x-header title="Transaction Analytics" subtitle="Comprehensive transaction analysis and insights">
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
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
                <x-button label="Refresh" icon="o-arrow-path" wire:click="refreshData" class="btn-primary" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Export Report" icon="o-arrow-down-tray" wire:click="exportReport" class="btn-outline" />
            <x-button label="Back to Transactions" icon="o-arrow-left" link="/transactions" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    {{-- Quick Navigation Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
        <x-card class="p-4 transition-all duration-200 cursor-pointer hover:shadow-lg" onclick="window.location.href='/transactions/volume-analysis'">
            <div class="flex items-center space-x-3">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <x-icon name="o-chart-bar" class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Volume Analysis</h3>
                    <p class="text-sm text-gray-500">Detailed volume trends</p>
                </div>
            </div>
        </x-card>

        <x-card class="p-4 transition-all duration-200 cursor-pointer hover:shadow-lg" onclick="window.location.href='/transactions/trend-analysis'">
            <div class="flex items-center space-x-3">
                <div class="p-3 bg-green-100 rounded-lg">
                    <x-icon name="o-arrow-trending-up" class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Trend Analysis</h3>
                    <p class="text-sm text-gray-500">Historical patterns</p>
                </div>
            </div>
        </x-card>

        <x-card class="p-4 transition-all duration-200 cursor-pointer hover:shadow-lg" onclick="window.location.href='/transactions/channel-analysis'">
            <div class="flex items-center space-x-3">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <x-icon name="o-device-phone-mobile" class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Channel Analysis</h3>
                    <p class="text-sm text-gray-500">Channel performance</p>
                </div>
            </div>
        </x-card>

        <x-card class="p-4 transition-all duration-200 cursor-pointer hover:shadow-lg" onclick="window.location.href='/transactions/performance-metrics'">
            <div class="flex items-center space-x-3">
                <div class="p-3 bg-orange-100 rounded-lg">
                    <x-icon name="o-clock" class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">Performance Metrics</h3>
                    <p class="text-sm text-gray-500">Speed & efficiency</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Overview Statistics --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
        <x-stat
            title="Total Transactions"
            :value="number_format($overviewStats['total_transactions'])"
            icon="o-queue-list"
            color="text-blue-500"
            :description="($overviewStats['transaction_growth'] >= 0 ? '+' : '') . $overviewStats['transaction_growth'] . '% vs previous period'" />

        <x-stat
            title="Total Volume"
            :value="number_format($overviewStats['total_volume'], 0) . ' DJF'"
            icon="o-banknotes"
            color="text-green-500"
            :description="($overviewStats['volume_growth'] >= 0 ? '+' : '') . $overviewStats['volume_growth'] . '% vs previous period'" />

        <x-stat
            title="Success Rate"
            :value="$overviewStats['success_rate'] . '%'"
            icon="o-check-circle"
            color="text-emerald-500"
            :description="number_format($overviewStats['successful_transactions']) . ' successful transactions'" />

        <x-stat
            title="Average Amount"
            :value="number_format($overviewStats['average_amount'], 0) . ' DJF'"
            icon="o-calculator"
            color="text-purple-500"
            :description="'Total fees: ' . number_format($overviewStats['total_fees'], 0) . ' DJF'" />
    </div>

    {{-- Status Breakdown and Daily Trends --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Transaction Status Breakdown --}}
        <x-card title="Transaction Status Breakdown" class="h-fit">
            <div class="space-y-4">
                @foreach($statusBreakdown as $status)
                    @php
                        $percentage = $overviewStats['total_transactions'] > 0 ? ($status['count'] / $overviewStats['total_transactions']) * 100 : 0;
                        $statusColor = match($status['trans_status']) {
                            'Completed' => 'bg-green-500',
                            'Failed' => 'bg-red-500',
                            'Pending', 'Pending Authorized' => 'bg-yellow-500',
                            'Cancelled' => 'bg-gray-500',
                            default => 'bg-blue-500'
                        };
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">{{ $status['trans_status'] }}</span>
                            <span class="text-sm text-gray-500">{{ number_format($status['count']) }} ({{ number_format($percentage, 1) }}%)</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full">
                            <div class="{{ $statusColor }} h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Volume: {{ number_format($status['volume'], 0) }} DJF
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Performance Summary --}}
        <x-card title="Performance Summary">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 text-center rounded-lg bg-blue-50">
                    <div class="text-2xl font-bold text-blue-600">{{ $performanceMetrics['avg_processing_time'] }}s</div>
                    <div class="text-sm text-gray-600">Avg Processing Time</div>
                </div>
                <div class="p-4 text-center rounded-lg bg-green-50">
                    <div class="text-2xl font-bold text-green-600">{{ $performanceMetrics['peak_hour'] }}:00</div>
                    <div class="text-sm text-gray-600">Peak Hour</div>
                    <div class="text-xs text-gray-500">{{ number_format($performanceMetrics['peak_hour_count']) }} transactions</div>
                </div>
                <div class="p-4 text-center rounded-lg bg-red-50">
                    <div class="text-2xl font-bold text-red-600">{{ $performanceMetrics['error_rate'] }}%</div>
                    <div class="text-sm text-gray-600">Error Rate</div>
                </div>
                <div class="p-4 text-center rounded-lg bg-purple-50">
                    <div class="text-2xl font-bold text-purple-600">{{ number_format($overviewStats['reversed_transactions']) }}</div>
                    <div class="text-sm text-gray-600">Reversals</div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Top Transaction Types --}}
    <x-card title="Top Transaction Types" class="mb-6">
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Transaction Type</th>
                        <th>Count</th>
                        <th>Total Volume</th>
                        <th>Average Amount</th>
                        <th>Market Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topTransactionTypes as $type)
                        @php
                            $marketShare = $overviewStats['total_transactions'] > 0 ? ($type['transaction_count'] / $overviewStats['total_transactions']) * 100 : 0;
                        @endphp
                        <tr>
                            <td>
                                <div class="font-medium">{{ $type['alias'] ?: $type['txn_type_name'] ?: 'Unknown' }}</div>
                            </td>
                            <td>{{ number_format($type['transaction_count']) }}</td>
                            <td>{{ number_format($type['total_volume'], 0) }} DJF</td>
                            <td>{{ number_format($type['avg_amount'], 0) }} DJF</td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <div class="w-20 h-2 bg-gray-200 rounded-full">
                                        <div class="h-2 bg-blue-500 rounded-full" style="width: {{ min($marketShare, 100) }}%"></div>
                                    </div>
                                    <span class="text-sm">{{ number_format($marketShare, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Currency Distribution and Channel Performance --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Currency Distribution --}}
        <x-card title="Currency Distribution">
            <div class="space-y-3">
                @foreach($currencyDistribution as $currency)
                    @php
                        $percentage = $overviewStats['total_volume'] > 0 ? ($currency['volume'] / $overviewStats['total_volume']) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium">{{ $currency['currency'] }}</span>
                            <span class="text-sm text-gray-500">{{ number_format($currency['count']) }} transactions</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full">
                            <div class="h-2 bg-green-500 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ number_format($currency['volume'], 0) }} {{ $currency['currency'] }} ({{ number_format($percentage, 1) }}%)
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Channel Performance Summary --}}
        <x-card title="Channel Performance">
            <div class="space-y-3">
                @foreach($channelPerformance as $channel)
                    <div class="p-3 border rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium">{{ $channel['channel'] ?: 'Unknown' }}</span>
                            <span class="text-sm px-2 py-1 rounded {{ $channel['success_rate'] >= 95 ? 'bg-green-100 text-green-800' : ($channel['success_rate'] >= 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ number_format($channel['success_rate'], 1) }}%
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-gray-500">Transactions</div>
                                <div class="font-medium">{{ number_format($channel['transaction_count']) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Volume</div>
                                <div class="font-medium">{{ number_format($channel['total_volume'], 0) }} DJF</div>
                            </div>
                        </div>
                        @if($channel['avg_processing_time'])
                            <div class="mt-2 text-xs text-gray-500">
                                Avg Processing: {{ number_format($channel['avg_processing_time'], 1) }}s
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- High Value Transactions --}}
    @if(count($highValueTransactions) > 0)
        <x-card title="Recent High Value Transactions (≥10,000 DJF)" class="mb-6">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Parties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($highValueTransactions as $transaction)
                            <tr>
                                <td>
                                    <x-button
                                        label="{{ $transaction['orderid'] }}"
                                        link="/transactions/{{ $transaction['orderid'] }}"
                                        class="font-mono btn-ghost btn-sm" />
                                </td>
                                <td>
                                    <div class="text-lg font-bold">{{ number_format($transaction['actual_amount'], 0) }}</div>
                                    <div class="text-sm text-gray-500">{{ $transaction['currency'] }}</div>
                                </td>
                                <td>
                                    <x-badge :value="$transaction['trans_status']" class="badge-{{ $transaction['status_color'] ?? 'gray' }}" />
                                </td>
                                <td>
                                    <div class="text-sm">{{ \Carbon\Carbon::parse($transaction['trans_initate_time'])->format('d/m/Y H:i') }}</div>
                                </td>
                                <td>
                                    <div class="text-sm">
                                        {{ $transaction['transaction_details']['transaction_type']['display_name'] ?? 'N/A' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-xs">
                                        <div><strong>From:</strong> {{ \Illuminate\Support\Str::limit($transaction['debit_party_mnemonic'], 15) }}</div>
                                        <div><strong>To:</strong> {{ \Illuminate\Support\Str::limit($transaction['credit_party_mnemonic'], 15) }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
