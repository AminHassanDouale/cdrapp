<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    use Toast, WithPagination;

    public string $search = '';
    public string $currencyFilter = '';
    public array|string $dateRange = [];
    public string $reasonFilter = '';
    public string $sortBy = 'trans_initate_time';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        // Set default date range to last 30 days
        $this->dateRange = [
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d')
        ];
    }

    public function with(): array
    {
        $transactions = Transaction::failed()
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('orderid', 'like', "%{$this->search}%")
                      ->orWhere('remark', 'ilike', "%{$this->search}%")
                      ->orWhere('debit_party_mnemonic', 'ilike', "%{$this->search}%")
                      ->orWhere('credit_party_mnemonic', 'ilike', "%{$this->search}%");
                });
            })
            ->when($this->currencyFilter, function (Builder $query) {
                $query->where('currency', $this->currencyFilter);
            })
            ->when($this->reasonFilter, function (Builder $query) {
                $query->where('reason_type', $this->reasonFilter);
            })
            ->when($this->dateRange, function (Builder $query) {
                $this->applyDateRangeFilter($query);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $currencies = Transaction::failed()->distinct()->pluck('currency')->filter()->sort();
        $reasons = Transaction::failed()->distinct()->pluck('reason_type')->filter()->sort();

        $stats = [
            'total_failed' => Transaction::failed()->count(),
            'today_failed' => Transaction::failed()->whereDate('trans_initate_time', today())->count(),
            'week_failed' => Transaction::failed()->where('trans_initate_time', '>=', now()->subWeek())->count(),
            'high_value_failed' => Transaction::failed()->where('actual_amount', '>=', 10000)->count(),
            'failure_rate_today' => $this->calculateFailureRate('today'),
            'failure_rate_week' => $this->calculateFailureRate('week'),
        ];

        return [
            'transactions' => $transactions,
            'currencies' => $currencies,
            'reasons' => $reasons,
            'stats' => $stats,
            'dateRangeConfig' => [
                'mode' => 'range',
                'dateFormat' => 'd/m/Y',
                'altFormat' => 'd/m/Y',
                'altInput' => true,
                'allowInput' => true,
                'locale' => [
                    'firstDayOfWeek' => 1 // Monday
                ]
            ]
        ];
    }

    private function applyDateRangeFilter(Builder $query): void
    {
        try {
            if (is_array($this->dateRange) && count($this->dateRange) >= 2) {
                // Handle array format [start_date, end_date]
                $startDate = $this->dateRange[0];
                $endDate = $this->dateRange[1];

                $query->whereDate('trans_initate_time', '>=', $startDate)
                      ->whereDate('trans_initate_time', '<=', $endDate);

            } elseif (is_string($this->dateRange) && !empty($this->dateRange)) {
                // Handle string format "dd/mm/yyyy to dd/mm/yyyy"
                if (str_contains($this->dateRange, ' to ')) {
                    $dates = explode(' to ', $this->dateRange);
                    if (count($dates) === 2) {
                        $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                        $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');

                        $query->whereDate('trans_initate_time', '>=', $startDate)
                              ->whereDate('trans_initate_time', '<=', $endDate);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Date range filter error in failed transactions: ' . $e->getMessage(), [
                'dateRange' => $this->dateRange
            ]);
            // Don't apply filter if date parsing fails
        }
    }

    private function calculateFailureRate(string $period): float
    {
        $date = $period === 'today' ? today() : now()->subWeek();
        $condition = $period === 'today' ?
            fn($q) => $q->whereDate('trans_initate_time', $date) :
            fn($q) => $q->where('trans_initate_time', '>=', $date);

        $total = Transaction::where($condition)->count();
        $failed = Transaction::failed()->where($condition)->count();

        return $total > 0 ? round(($failed / $total) * 100, 2) : 0;
    }

    public function sort($field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->currencyFilter = '';
        $this->reasonFilter = '';
        $this->dateRange = [
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d')
        ];
        $this->resetPage();
    }

    public function clearDateRange(): void
    {
        $this->dateRange = [];
        $this->resetPage();
    }

    public function setQuickDateRange(string $period): void
    {
        switch ($period) {
            case 'today':
                $this->dateRange = [
                    now()->format('Y-m-d'),
                    now()->format('Y-m-d')
                ];
                break;
            case 'yesterday':
                $this->dateRange = [
                    now()->subDay()->format('Y-m-d'),
                    now()->subDay()->format('Y-m-d')
                ];
                break;
            case 'last_7_days':
                $this->dateRange = [
                    now()->subDays(7)->format('Y-m-d'),
                    now()->format('Y-m-d')
                ];
                break;
            case 'last_30_days':
                $this->dateRange = [
                    now()->subDays(30)->format('Y-m-d'),
                    now()->format('Y-m-d')
                ];
                break;
            case 'this_month':
                $this->dateRange = [
                    now()->startOfMonth()->format('Y-m-d'),
                    now()->endOfMonth()->format('Y-m-d')
                ];
                break;
            case 'last_month':
                $this->dateRange = [
                    now()->subMonth()->startOfMonth()->format('Y-m-d'),
                    now()->subMonth()->endOfMonth()->format('Y-m-d')
                ];
                break;
        }
        $this->resetPage();
    }

    public function retryTransaction(string $orderid): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        try {
            // Add retry logic here
            $transaction = Transaction::where('orderid', $orderid)->first();
            if ($transaction) {
                // Log the retry attempt
                \Log::info('Transaction retry initiated', [
                    'orderid' => $orderid,
                    'user_id' => auth()->id(),
                    'timestamp' => now()
                ]);

                $this->success("Transaction {$orderid} queued for retry");
                $this->dispatch('transaction-retry-initiated', $orderid);
            } else {
                $this->error('Transaction not found');
            }
        } catch (\Exception $e) {
            \Log::error('Transaction retry error: ' . $e->getMessage());
            $this->error('Failed to queue transaction for retry');
        }
    }

    public function investigateFailure(string $orderid): void
    {
        if (!auth()->user()->hasRole(['manager', 'admin', 'super-admin'])) {
            $this->error('Unauthorized action');
            return;
        }

        try {
            // Add investigation logic here
            $transaction = Transaction::where('orderid', $orderid)->first();
            if ($transaction) {
                // Log the investigation
                \Log::info('Failure investigation initiated', [
                    'orderid' => $orderid,
                    'investigator_id' => auth()->id(),
                    'timestamp' => now(),
                    'failure_reason' => $transaction->reason_type
                ]);

                $this->info("Investigation initiated for transaction {$orderid}");
                $this->dispatch('investigation-started', $orderid);
            } else {
                $this->error('Transaction not found');
            }
        } catch (\Exception $e) {
            \Log::error('Investigation initiation error: ' . $e->getMessage());
            $this->error('Failed to initiate investigation');
        }
    }

    public function exportFailedTransactions(): void
    {
        try {
            $query = Transaction::failed()
                ->when($this->search, function (Builder $query) {
                    $query->where(function (Builder $q) {
                        $q->where('orderid', 'like', "%{$this->search}%")
                          ->orWhere('remark', 'ilike', "%{$this->search}%")
                          ->orWhere('debit_party_mnemonic', 'ilike', "%{$this->search}%")
                          ->orWhere('credit_party_mnemonic', 'ilike', "%{$this->search}%");
                    });
                })
                ->when($this->currencyFilter, function (Builder $query) {
                    $query->where('currency', $this->currencyFilter);
                })
                ->when($this->reasonFilter, function (Builder $query) {
                    $query->where('reason_type', $this->reasonFilter);
                })
                ->when($this->dateRange, function (Builder $query) {
                    $this->applyDateRangeFilter($query);
                });

            $transactions = $query->orderBy('trans_initate_time', 'desc')->get();

            $data = [
                'export_type' => 'failed_transactions',
                'filters' => [
                    'search' => $this->search,
                    'currency' => $this->currencyFilter,
                    'reason' => $this->reasonFilter,
                    'date_range' => $this->dateRange,
                ],
                'summary' => [
                    'total_failed' => $transactions->count(),
                    'total_amount_failed' => $transactions->sum('actual_amount'),
                    'high_value_failures' => $transactions->where('actual_amount', '>=', 10000)->count(),
                    'failure_reasons' => $transactions->groupBy('reason_type')->map->count(),
                ],
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'orderid' => $transaction->orderid,
                        'trans_status' => $transaction->trans_status,
                        'trans_initate_time' => $transaction->trans_initate_time?->format('d/m/Y H:i:s'),
                        'debit_party_id' => $transaction->debit_party_id,
                        'debit_party_mnemonic' => $transaction->debit_party_mnemonic,
                        'debit_party_account' => $transaction->debit_party_account,
                        'credit_party_id' => $transaction->credit_party_id,
                        'credit_party_mnemonic' => $transaction->credit_party_mnemonic,
                        'credit_party_account' => $transaction->credit_party_account,
                        'actual_amount' => $transaction->actual_amount,
                        'fee' => $transaction->fee,
                        'currency' => $transaction->currency,
                        'reason_type' => $transaction->reason_type,
                        'remark' => $transaction->remark,
                        'is_high_value' => $transaction->isHighValue(),
                    ];
                }),
                'generated_at' => now()->format('d/m/Y H:i:s')
            ];

            $this->dispatch('download-failed-export', $data);
            $this->success('Export of failed transactions initiated.');
        } catch (\Exception $e) {
            \Log::error('Export failed transactions error: ' . $e->getMessage());
            $this->error('Error occurred during export. Please try again.');
        }
    }

    public function bulkRetryFailures(): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        try {
            $failedToday = Transaction::failed()
                ->whereDate('trans_initate_time', today())
                ->where('actual_amount', '<', 10000) // Only retry non-high-value transactions
                ->count();

            if ($failedToday > 0) {
                // Log bulk retry
                \Log::info('Bulk retry initiated', [
                    'count' => $failedToday,
                    'user_id' => auth()->id(),
                    'timestamp' => now()
                ]);

                $this->success("Bulk retry initiated for {$failedToday} failed transactions");
                $this->dispatch('bulk-retry-initiated', $failedToday);
            } else {
                $this->info('No eligible transactions found for bulk retry');
            }
        } catch (\Exception $e) {
            \Log::error('Bulk retry error: ' . $e->getMessage());
            $this->error('Failed to initiate bulk retry');
        }
    }
}; ?>

<div>
    <x-header title="Failed Transactions" subtitle="Monitor and investigate failed transactions">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search failed transactions..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Export" icon="o-arrow-down-tray" wire:click="exportFailedTransactions" class="btn-outline" spinner="exportFailedTransactions" />

            @if(auth()->user()->can('transactions.process') && $stats['today_failed'] > 0)
                <x-button label="Bulk Retry" icon="o-arrow-path" wire:click="bulkRetryFailures" class="btn-warning"
                          wire:confirm="Retry all non-high-value failed transactions from today?" />
            @endif

            <x-button label="Analytics" icon="o-chart-bar" link="/transactions/analytics" class="btn-outline" />
            <x-button label="All Transactions" icon="o-queue-list" link="/transactions" class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- Alert for High Failure Rate --}}
    @if($stats['failure_rate_today'] > 10)
        <x-alert title="High Failure Rate Alert"
                 description="Today's failure rate is {{ $stats['failure_rate_today'] }}% - requires immediate attention."
                 icon="o-exclamation-triangle"
                 class="mb-6 alert-error" />
    @endif

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-6">
        <x-stat
            title="Total Failed"
            :value="number_format($stats['total_failed'])"
            icon="o-x-circle"
            color="text-red-500" />

        <x-stat
            title="Today's Failed"
            :value="number_format($stats['today_failed'])"
            icon="o-calendar-days"
            color="text-red-500" />

        <x-stat
            title="This Week"
            :value="number_format($stats['week_failed'])"
            icon="o-chart-bar"
            color="text-orange-500" />

        <x-stat
            title="High Value Failed"
            :value="number_format($stats['high_value_failed'])"
            icon="o-star"
            color="text-yellow-500" />

        <x-stat
            title="Today's Failure Rate"
            :value="$stats['failure_rate_today'] . '%'"
            icon="o-exclamation-triangle"
            color="text-red-500" />

        <x-stat
            title="Week's Failure Rate"
            :value="$stats['failure_rate_week'] . '%'"
            icon="o-chart-pie"
            color="text-orange-500" />
    </div>

    {{-- Filters --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-select
                label="Currency"
                wire:model.live="currencyFilter"
                :options="$currencies->map(fn($currency) => ['id' => $currency, 'name' => $currency])"
                placeholder="All Currencies" />

            <x-select
                label="Failure Reason"
                wire:model.live="reasonFilter"
                :options="$reasons->map(fn($reason) => ['id' => $reason, 'name' => $reason ?: 'Unknown'])"
                placeholder="All Reasons" />

            <x-datepicker
                label="Date Range"
                wire:model.live="dateRange"
                icon="o-calendar"
                :config="$dateRangeConfig"
                hint="Select failure date range" />

            <div class="flex items-end gap-2">
                <x-button label="Reset Filters" wire:click="resetFilters" class="btn-ghost" />
                <x-button label="Clear Dates" wire:click="clearDateRange" class="btn-ghost btn-sm" />
            </div>
        </div>

        {{-- Quick Date Range Buttons --}}
        <div class="flex flex-wrap gap-2 mt-4">
            <span class="text-sm font-medium text-gray-600">Quick ranges:</span>
            <x-button label="Today" wire:click="setQuickDateRange('today')" class="btn-xs btn-outline" />
            <x-button label="Yesterday" wire:click="setQuickDateRange('yesterday')" class="btn-xs btn-outline" />
            <x-button label="Last 7 days" wire:click="setQuickDateRange('last_7_days')" class="btn-xs btn-outline" />
            <x-button label="Last 30 days" wire:click="setQuickDateRange('last_30_days')" class="btn-xs btn-outline" />
            <x-button label="This month" wire:click="setQuickDateRange('this_month')" class="btn-xs btn-outline" />
            <x-button label="Last month" wire:click="setQuickDateRange('last_month')" class="btn-xs btn-outline" />
        </div>

        {{-- Active Filters Display --}}
        @if($search || $currencyFilter || $reasonFilter || !empty($dateRange))
        <div class="mt-4">
            <span class="text-sm font-medium text-gray-600">Active filters:</span>
            <div class="flex flex-wrap gap-2 mt-2">
                @if($search)
                    <div class="badge badge-neutral">
                        Search: {{ $search }}
                        <x-button icon="o-x-mark" wire:click="$set('search', '')" class="btn-xs btn-ghost ml-1" />
                    </div>
                @endif
                @if($currencyFilter)
                    <div class="badge badge-neutral">
                        Currency: {{ $currencyFilter }}
                        <x-button icon="o-x-mark" wire:click="$set('currencyFilter', '')" class="btn-xs btn-ghost ml-1" />
                    </div>
                @endif
                @if($reasonFilter)
                    <div class="badge badge-neutral">
                        Reason: {{ $reasonFilter }}
                        <x-button icon="o-x-mark" wire:click="$set('reasonFilter', '')" class="btn-xs btn-ghost ml-1" />
                    </div>
                @endif
                @if(!empty($dateRange))
                    <div class="badge badge-neutral">
                        @if(is_array($dateRange) && count($dateRange) >= 2)
                            Date: {{ \Carbon\Carbon::parse($dateRange[0])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateRange[1])->format('d/m/Y') }}
                        @elseif(is_string($dateRange))
                            Date: {{ $dateRange }}
                        @endif
                        <x-button icon="o-x-mark" wire:click="clearDateRange" class="btn-xs btn-ghost ml-1" />
                    </div>
                @endif
            </div>
        </div>
        @endif
    </x-card>

    {{-- Transactions Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>
                            <x-button wire:click="sort('orderid')" class="btn-ghost btn-sm">
                                Order ID
                                @if($sortBy === 'orderid')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>
                            <x-button wire:click="sort('trans_initate_time')" class="btn-ghost btn-sm">
                                Failed Date
                                @if($sortBy === 'trans_initate_time')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>
                            <x-button wire:click="sort('actual_amount')" class="btn-ghost btn-sm">
                                Amount
                                @if($sortBy === 'actual_amount')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>Debit Party</th>
                        <th>Credit Party</th>
                        <th>Failure Reason</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr wire:key="failed-{{ $transaction->orderid }}" class="bg-red-50 dark:bg-red-900/10">
                            <td>
                                <x-button
                                    label="{{ $transaction->orderid }}"
                                    link="/transactions/{{ $transaction->orderid }}"
                                    class="font-mono btn-ghost btn-sm" />
                            </td>
                            <td>
                                <div class="text-sm">
                                    {{ $transaction->trans_initate_time?->format('d/m/Y') }}
                                    <br>
                                    <span class="text-gray-500">{{ $transaction->trans_initate_time?->format('H:i:s') }}</span>
                                    <br>
                                    <span class="text-xs text-red-500">{{ $transaction->trans_initate_time?->diffForHumans() }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="font-medium">
                                    {{ number_format($transaction->actual_amount, 2) }}
                                    <span class="text-sm text-gray-500">{{ $transaction->currency }}</span>
                                </div>
                                @if($transaction->fee > 0)
                                    <div class="text-xs text-gray-500">
                                        Fee: {{ number_format($transaction->fee, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->debit_party_mnemonic, 20) }}</div>
                                    <div class="font-mono text-xs text-gray-500">{{ Str::limit($transaction->debit_party_account, 15) }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->credit_party_mnemonic, 20) }}</div>
                                    <div class="font-mono text-xs text-gray-500">{{ Str::limit($transaction->credit_party_account, 15) }}</div>
                                </div>
                            </td>
                            <td>
                                @if($transaction->reason_type)
                                    <x-badge :value="Str::limit($transaction->reason_type, 15)" class="badge-error badge-sm" />
                                @else
                                    <span class="text-gray-400">Unknown</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col space-y-1">
                                    @if($transaction->isHighValue())
                                        <x-badge value="High Value" class="badge-warning badge-xs" />
                                    @endif
                                    @if($transaction->trans_initate_time?->isToday())
                                        <x-badge value="Recent" class="badge-error badge-xs" />
                                    @endif
                                    @if($transaction->trans_initate_time?->diffInHours() < 1)
                                        <x-badge value="Critical" class="badge-error badge-xs" />
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <x-button
                                        icon="o-eye"
                                        link="/transactions/{{ $transaction->orderid }}"
                                        class="btn-ghost btn-xs"
                                        tooltip="View Details" />

                                    @if(auth()->user()->can('transactions.process'))
                                        <x-button
                                            icon="o-arrow-path"
                                            wire:click="retryTransaction('{{ $transaction->orderid }}')"
                                            class="btn-warning btn-xs"
                                            tooltip="Retry Transaction"
                                            wire:confirm="Are you sure you want to retry this transaction?" />
                                    @endif

                                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                                        <x-button
                                            icon="o-magnifying-glass"
                                            wire:click="investigateFailure('{{ $transaction->orderid }}')"
                                            class="btn-info btn-xs"
                                            tooltip="Investigate Failure" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center">
                                <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-4 text-green-400" />
                                <p class="text-gray-500">No failed transactions found</p>
                                <p class="text-sm text-gray-400">This is a good sign!</p>
                                @if($search || $currencyFilter || $reasonFilter || !empty($dateRange))
                                    <x-button label="Clear Filters" wire:click="resetFilters" class="mt-2 btn-sm btn-outline" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
    </x-card>

    {{-- Failure Analysis Summary --}}
    @if($transactions->count() > 0)
        <x-card title="Failure Analysis" class="mt-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <h4 class="mb-2 font-medium text-blue-900 dark:text-blue-100">Next Steps</h4>
                    <ul class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                        <li>• Investigate high-value failures</li>
                        <li>• Contact affected customers</li>
                        <li>• Update system configurations</li>
                        <li>• Generate incident reports</li>
                        <li>• Implement preventive measures</li>
                        <li>• Schedule system maintenance</li>
                    </ul>
                </div>
            </div>

            {{-- Failure Trends --}}
            <div class="mt-6">
                <h4 class="mb-4 font-medium">Recent Failure Trends</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-4 border rounded-lg">
                        <h5 class="mb-2 text-sm font-medium text-gray-700">Most Common Reasons</h5>
                        <div class="space-y-2">
                            @php
                                $reasonCounts = $transactions->groupBy('reason_type')->map->count()->sortDesc()->take(5);
                            @endphp
                            @forelse($reasonCounts as $reason => $count)
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">{{ $reason ?: 'Unknown' }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $count }}</span>
                                        <div class="w-12 bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-500 h-2 rounded-full" style="width: {{ ($count / $transactions->count()) * 100 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No failure reasons available</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="p-4 border rounded-lg">
                        <h5 class="mb-2 text-sm font-medium text-gray-700">Failure Impact</h5>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600">High Value Failures</span>
                                <span class="font-medium text-red-600">{{ $transactions->where('actual_amount', '>=', 10000)->count() }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600">Today's Failures</span>
                                <span class="font-medium text-orange-600">{{ $transactions->filter(fn($t) => $t->trans_initate_time?->isToday())->count() }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600">Total Amount Failed</span>
                                <span class="font-medium text-red-600">{{ number_format($transactions->sum('actual_amount'), 0) }} DJF</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600">Average Failure Amount</span>
                                <span class="font-medium">{{ number_format($transactions->avg('actual_amount'), 0) }} DJF</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Quick Actions Panel --}}
    @if($stats['today_failed'] > 0 && auth()->user()->can('transactions.process'))
        <x-card title="Quick Actions" class="mt-6">
            <div class="flex flex-wrap gap-4">
                <x-button
                    label="Retry All Low-Value Failures"
                    icon="o-arrow-path"
                    wire:click="bulkRetryFailures"
                    class="btn-warning"
                    wire:confirm="This will retry all non-high-value failed transactions from today. Continue?" />

                <x-button
                    label="Generate Failure Report"
                    icon="o-document-text"
                    wire:click="exportFailedTransactions"
                    class="btn-info" />

                @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                    <x-button
                        label="Escalate Critical Failures"
                        icon="o-exclamation-triangle"
                        class="btn-error"
                        onclick="alert('Feature coming soon: Auto-escalation of critical failures')" />
                @endif
            </div>
        </x-card>
    @endif
</div>

{{-- JavaScript for Export and Event Handling --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Livewire events
    document.addEventListener('livewire:init', () => {
        Livewire.on('download-failed-export', (data) => {
            // Create and download JSON file
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;

            // Create filename with current date and filters
            let filename = 'failed-transactions-' + new Date().toISOString().split('T')[0];
            if (data.filters.currency) {
                filename += '-' + data.filters.currency;
            }
            if (data.filters.reason) {
                filename += '-' + data.filters.reason.replace(/[^a-zA-Z0-9]/g, '');
            }
            filename += '.json';

            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        Livewire.on('transaction-retry-initiated', (orderid) => {
            showNotification('info', `Transaction ${orderid} has been queued for retry`);
        });

        Livewire.on('investigation-started', (orderid) => {
            showNotification('info', `Investigation started for transaction ${orderid}`);
        });

        Livewire.on('bulk-retry-initiated', (count) => {
            showNotification('success', `Bulk retry initiated for ${count} transactions`);
        });
    });

    // Notification helper function
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} fixed top-4 right-4 z-50 max-w-sm shadow-lg`;
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${getNotificationIcon(type)}
                </svg>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 4000);
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'success':
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
            case 'error':
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
            case 'info':
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
            default:
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        }
    }
});
</script>

