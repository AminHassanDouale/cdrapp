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
    public string $amountFrom = '';
    public string $amountTo = '';
    public string $sortBy = 'trans_initate_time';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        // Set default date range to last 7 days
        $this->dateRange = [
            now()->subDays(7)->format('Y-m-d'),
            now()->format('Y-m-d')
        ];
    }

    public function with(): array
    {
        $transactions = Transaction::successful()
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
            ->when($this->dateRange, function (Builder $query) {
                $this->applyDateRangeFilter($query);
            })
            ->when($this->amountFrom, function (Builder $query) {
                $query->where('actual_amount', '>=', $this->amountFrom);
            })
            ->when($this->amountTo, function (Builder $query) {
                $query->where('actual_amount', '<=', $this->amountTo);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $currencies = Transaction::successful()->distinct()->pluck('currency')->filter()->sort();

        $stats = [
            'total_completed' => Transaction::successful()->count(),
            'today_completed' => Transaction::successful()->whereDate('trans_initate_time', today())->count(),
            'today_volume' => Transaction::successful()->whereDate('trans_initate_time', today())->sum('actual_amount'),
            'week_volume' => Transaction::successful()->where('trans_initate_time', '>=', now()->subWeek())->sum('actual_amount'),
            'high_value_today' => Transaction::successful()->whereDate('trans_initate_time', today())->where('actual_amount', '>=', 10000)->count(),
            'avg_amount' => Transaction::successful()->whereDate('trans_initate_time', today())->avg('actual_amount'),
        ];

        return [
            'transactions' => $transactions,
            'currencies' => $currencies,
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
            \Log::error('Date range filter error: ' . $e->getMessage(), [
                'dateRange' => $this->dateRange
            ]);
            // Don't apply filter if date parsing fails
        }
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
        $this->dateRange = [
            now()->subDays(7)->format('Y-m-d'),
            now()->format('Y-m-d')
        ];
        $this->amountFrom = '';
        $this->amountTo = '';
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
            case 'this_year':
                $this->dateRange = [
                    now()->startOfYear()->format('Y-m-d'),
                    now()->endOfYear()->format('Y-m-d')
                ];
                break;
            case 'last_year':
                $this->dateRange = [
                    now()->subYear()->startOfYear()->format('Y-m-d'),
                    now()->subYear()->endOfYear()->format('Y-m-d')
                ];
                break;
        }
        $this->resetPage();
    }

    public function exportCompleted(): void
    {
        try {
            $query = Transaction::successful()
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
                ->when($this->dateRange, function (Builder $query) {
                    $this->applyDateRangeFilter($query);
                })
                ->when($this->amountFrom, function (Builder $query) {
                    $query->where('actual_amount', '>=', $this->amountFrom);
                })
                ->when($this->amountTo, function (Builder $query) {
                    $query->where('actual_amount', '<=', $this->amountTo);
                });

            $transactions = $query->orderBy('trans_initate_time', 'desc')->get();

            $data = [
                'export_type' => 'completed_transactions',
                'filters' => [
                    'search' => $this->search,
                    'currency' => $this->currencyFilter,
                    'date_range' => $this->dateRange,
                    'amount_from' => $this->amountFrom,
                    'amount_to' => $this->amountTo,
                ],
                'summary' => [
                    'total_transactions' => $transactions->count(),
                    'total_amount' => $transactions->sum('actual_amount'),
                    'total_fees' => $transactions->sum('fee'),
                ],
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'orderid' => $transaction->orderid,
                        'trans_status' => $transaction->trans_status,
                        'trans_initate_time' => $transaction->trans_initate_time?->format('d/m/Y H:i:s'),
                        'trans_end_time' => $transaction->trans_end_time !== 'NULL' ? $transaction->trans_end_time : null,
                        'debit_party_id' => $transaction->debit_party_id,
                        'debit_party_mnemonic' => $transaction->debit_party_mnemonic,
                        'debit_party_account' => $transaction->debit_party_account,
                        'credit_party_id' => $transaction->credit_party_id,
                        'credit_party_mnemonic' => $transaction->credit_party_mnemonic,
                        'credit_party_account' => $transaction->credit_party_account,
                        'actual_amount' => $transaction->actual_amount,
                        'fee' => $transaction->fee,
                        'currency' => $transaction->currency,
                        'is_reversed' => $transaction->is_reversed,
                        'remark' => $transaction->remark,
                    ];
                }),
                'generated_at' => now()->format('d/m/Y H:i:s')
            ];

            $this->dispatch('download-completed-export', $data);
            $this->success('Export of completed transactions initiated.');
        } catch (\Exception $e) {
            \Log::error('Export completed transactions error: ' . $e->getMessage());
            $this->error('Error occurred during export. Please try again.');
        }
    }
}; ?>

<div>
    <x-header title="Completed Transactions" subtitle="View all successfully completed transactions">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search completed transactions..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Export" icon="o-arrow-down-tray" wire:click="exportCompleted" class="btn-outline" spinner="exportCompleted" />
            <x-button label="All Transactions" icon="o-queue-list" link="/transactions" class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-6">
        <x-stat
            title="Total Completed"
            :value="number_format($stats['total_completed'])"
            icon="o-check-circle"
            color="text-green-500" />

        <x-stat
            title="Today's Completed"
            :value="number_format($stats['today_completed'])"
            icon="o-calendar-days"
            color="text-blue-500" />

        <x-stat
            title="Today's Volume"
            :value="number_format($stats['today_volume'], 0) . ' DJF'"
            icon="o-banknotes"
            color="text-green-500" />

        <x-stat
            title="Week's Volume"
            :value="number_format($stats['week_volume'], 0) . ' DJF'"
            icon="o-chart-bar"
            color="text-purple-500" />

        <x-stat
            title="High Value Today"
            :value="number_format($stats['high_value_today'])"
            icon="o-star"
            color="text-yellow-500" />

        <x-stat
            title="Average Amount"
            :value="number_format($stats['avg_amount'], 0) . ' DJF'"
            icon="o-calculator"
            color="text-indigo-500" />
    </div>

    {{-- Filters --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            <x-select
                label="Currency"
                wire:model.live="currencyFilter"
                :options="$currencies->map(fn($currency) => ['id' => $currency, 'name' => $currency])"
                placeholder="All Currencies" />

            <x-datepicker
                label="Date Range"
                wire:model.live="dateRange"
                icon="o-calendar"
                :config="$dateRangeConfig"
                hint="Select completion date range" />

            <x-input
                label="Amount From"
                wire:model.live.debounce="amountFrom"
                type="number"
                step="0.01"
                icon="o-currency-dollar" />

            <x-input
                label="Amount To"
                wire:model.live.debounce="amountTo"
                type="number"
                step="0.01"
                icon="o-currency-dollar" />

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
            <x-button label="This year" wire:click="setQuickDateRange('this_year')" class="btn-xs btn-outline" />
            <x-button label="Last year" wire:click="setQuickDateRange('last_year')" class="btn-xs btn-outline" />
        </div>

        {{-- Active Filters Display --}}
        @if($search || $currencyFilter || !empty($dateRange) || $amountFrom || $amountTo)
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
                @if($amountFrom)
                    <div class="badge badge-neutral">
                        Amount From: {{ number_format($amountFrom, 2) }}
                        <x-button icon="o-x-mark" wire:click="$set('amountFrom', '')" class="btn-xs btn-ghost ml-1" />
                    </div>
                @endif
                @if($amountTo)
                    <div class="badge badge-neutral">
                        Amount To: {{ number_format($amountTo, 2) }}
                        <x-button icon="o-x-mark" wire:click="$set('amountTo', '')" class="btn-xs btn-ghost ml-1" />
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
                                Completed Date
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
                        <th>Processing Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr wire:key="completed-{{ $transaction->orderid }}">
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
                                    <span class="text-xs text-gray-400">{{ $transaction->trans_initate_time?->diffForHumans() }}</span>
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
                                @if($transaction->isHighValue())
                                    <x-badge value="High Value" class="badge-warning badge-xs" />
                                @endif
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->debit_party_mnemonic, 25) }}</div>
                                    <div class="font-mono text-xs text-gray-500">{{ Str::limit($transaction->debit_party_account, 15) }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->credit_party_mnemonic, 25) }}</div>
                                    <div class="font-mono text-xs text-gray-500">{{ Str::limit($transaction->credit_party_account, 15) }}</div>
                                </div>
                            </td>
                            <td>
                                @if($transaction->trans_end_time && $transaction->trans_end_time !== 'NULL')
                                    @php
                                        try {
                                            $endTime = is_string($transaction->trans_end_time) ?
                                                \Carbon\Carbon::parse($transaction->trans_end_time) :
                                                $transaction->trans_end_time;
                                            $processingMinutes = $transaction->trans_initate_time?->diffInMinutes($endTime);
                                        } catch (\Exception $e) {
                                            $processingMinutes = null;
                                        }
                                    @endphp
                                    <div class="text-sm">
                                        <span class="text-green-600">
                                            {{ $processingMinutes ? $processingMinutes . ' min' : 'N/A' }}
                                        </span>
                                        @if($processingMinutes && $processingMinutes < 1)
                                            <div class="text-xs text-green-500">Instant</div>
                                        @elseif($processingMinutes && $processingMinutes > 60)
                                            <div class="text-xs text-orange-500">Slow</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    <x-button
                                        icon="o-eye"
                                        link="/transactions/{{ $transaction->orderid }}"
                                        class="btn-ghost btn-xs"
                                        tooltip="View Details" />

                                    @if($transaction->isHighValue())
                                        <x-icon name="o-star" class="w-4 h-4 text-yellow-500" tooltip="High Value" />
                                    @endif

                                    @if($transaction->is_reversed)
                                        <x-icon name="o-arrow-uturn-left" class="w-4 h-4 text-red-500" tooltip="Reversed" />
                                    @endif

                                    @if($transaction->isReversible() && auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                                        <x-button
                                            icon="o-arrow-uturn-left"
                                            class="text-orange-600 btn-ghost btn-xs"
                                            tooltip="Reverse Transaction"
                                            link="/transactions/{{ $transaction->orderid }}/reverse" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center">
                                <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
                                <p class="text-gray-500">No completed transactions found</p>
                                @if($search || $currencyFilter || $amountFrom || $amountTo || !empty($dateRange))
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
</div>

{{-- JavaScript for Export functionality --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Livewire events
    document.addEventListener('livewire:init', () => {
        Livewire.on('download-completed-export', (data) => {
            // Create and download JSON file
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;

            // Create filename with current date and filters
            let filename = 'completed-transactions-' + new Date().toISOString().split('T')[0];
            if (data.filters.currency) {
                filename += '-' + data.filters.currency;
            }
            filename += '.json';

            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    });
});
</script>
