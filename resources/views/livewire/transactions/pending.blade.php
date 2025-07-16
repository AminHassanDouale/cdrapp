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
    public string $sortBy = 'trans_initate_time';
    public string $sortDirection = 'desc';
    public array $selectedTransactions = [];
    public bool $selectAll = false;

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
        $transactions = Transaction::pending()
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
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        $currencies = Transaction::pending()->distinct()->pluck('currency')->filter()->sort();

        $stats = [
            'total_pending' => Transaction::pending()->count(),
            'high_value_pending' => Transaction::pending()->where('actual_amount', '>=', 10000)->count(),
            'expired_pending' => Transaction::pending()->where('expired_time', '<', now())->count(),
            'today_pending' => Transaction::pending()->whereDate('trans_initate_time', today())->count(),
            'total_value' => Transaction::pending()->sum('actual_amount'),
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

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedTransactions = $this->transactions->pluck('orderid')->toArray();
        } else {
            $this->selectedTransactions = [];
        }
    }

    public function approveTransaction(string $orderid): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        // Add approval logic here
        $this->success("Transaction {$orderid} approved successfully");
        $this->resetPage();
    }

    public function rejectTransaction(string $orderid): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        // Add rejection logic here
        $this->success("Transaction {$orderid} rejected");
        $this->resetPage();
    }

    public function bulkApprove(): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        if (empty($this->selectedTransactions)) {
            $this->warning('Please select transactions to approve');
            return;
        }

        // Add bulk approval logic here
        $count = count($this->selectedTransactions);
        $this->success("{$count} transactions approved successfully");
        $this->selectedTransactions = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function bulkReject(): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        if (empty($this->selectedTransactions)) {
            $this->warning('Please select transactions to reject');
            return;
        }

        // Add bulk rejection logic here
        $count = count($this->selectedTransactions);
        $this->success("{$count} transactions rejected");
        $this->selectedTransactions = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->currencyFilter = '';
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
}; ?>

<div>
    <x-header title="Pending Transactions" subtitle="Review and process pending transactions">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search pending transactions..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                @if(count($selectedTransactions) > 0)
                    <x-button label="Bulk Approve" icon="o-check" wire:click="bulkApprove" class="btn-success"
                              wire:confirm="Are you sure you want to approve {{ count($selectedTransactions) }} transactions?" />
                    <x-button label="Bulk Reject" icon="o-x-mark" wire:click="bulkReject" class="btn-error"
                              wire:confirm="Are you sure you want to reject {{ count($selectedTransactions) }} transactions?" />
                @endif
            @endif
            <x-button label="All Transactions" icon="o-queue-list" link="/transactions" class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-5">
        <x-stat
            title="Total Pending"
            :value="number_format($stats['total_pending'])"
            icon="o-clock"
            color="text-yellow-500" />

        <x-stat
            title="Today's Pending"
            :value="number_format($stats['today_pending'])"
            icon="o-calendar-days"
            color="text-blue-500" />

        <x-stat
            title="High Value"
            :value="number_format($stats['high_value_pending'])"
            icon="o-star"
            color="text-orange-500" />

        <x-stat
            title="Expired"
            :value="number_format($stats['expired_pending'])"
            icon="o-exclamation-triangle"
            color="text-red-500" />

        <x-stat
            title="Total Value"
            :value="number_format($stats['total_value'], 0) . ' DJF'"
            icon="o-banknotes"
            color="text-green-500" />
    </div>

    {{-- Alert for Expired Transactions --}}
    @if($stats['expired_pending'] > 0)
        <x-alert title="Expired Transactions" description="{{ $stats['expired_pending'] }} transactions have expired and require immediate attention."
                 icon="o-exclamation-triangle" class="mb-6 alert-warning" />
    @endif

    {{-- Filters --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                hint="Select date range for transactions" />

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
        @if($search || $currencyFilter || !empty($dateRange))
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
                        @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                            <th>
                                <x-checkbox wire:model.live="selectAll" wire:click="toggleSelectAll" />
                            </th>
                        @endif
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
                                Initiated
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
                        <th>Expires</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr wire:key="pending-{{ $transaction->orderid }}"
                            class="{{ $transaction->expired_time && $transaction->expired_time->isPast() ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                            @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                                <td>
                                    <x-checkbox wire:model.live="selectedTransactions" value="{{ $transaction->orderid }}" />
                                </td>
                            @endif
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
                                @if($transaction->expired_time)
                                    <div class="text-sm {{ $transaction->expired_time->isPast() ? 'text-red-600' : 'text-gray-600' }}">
                                        {{ $transaction->expired_time->format('d/m/Y H:i') }}
                                        <br>
                                        <span class="text-xs">
                                            {{ $transaction->expired_time->diffForHumans() }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-gray-400">No expiry</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col space-y-1">
                                    @if($transaction->isHighValue())
                                        <x-badge value="High Value" class="badge-warning badge-xs" />
                                    @endif
                                    @if($transaction->expired_time && $transaction->expired_time->isPast())
                                        <x-badge value="Expired" class="badge-error badge-xs" />
                                    @endif
                                    @if($transaction->expired_time && $transaction->expired_time->diffInHours() < 2)
                                        <x-badge value="Urgent" class="badge-warning badge-xs" />
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

                                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                                        <x-button
                                            icon="o-check"
                                            wire:click="approveTransaction('{{ $transaction->orderid }}')"
                                            class="btn-success btn-xs"
                                            tooltip="Approve"
                                            wire:confirm="Are you sure you want to approve this transaction?" />

                                        <x-button
                                            icon="o-x-mark"
                                            wire:click="rejectTransaction('{{ $transaction->orderid }}')"
                                            class="btn-error btn-xs"
                                            tooltip="Reject"
                                            wire:confirm="Are you sure you want to reject this transaction?" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasRole(['manager', 'admin', 'super-admin']) ? '9' : '8' }}" class="py-8 text-center">
                                <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-4 text-green-400" />
                                <p class="text-gray-500">No pending transactions found</p>
                                @if($search || $currencyFilter || !empty($dateRange))
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

    {{-- Selected Transactions Summary --}}
    @if(count($selectedTransactions) > 0)
        <div class="fixed p-4 bg-white border rounded-lg shadow-lg bottom-4 right-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center space-x-4">
                <span class="text-sm font-medium">{{ count($selectedTransactions) }} transactions selected</span>
                @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                    <x-button label="Approve All" wire:click="bulkApprove" class="btn-success btn-sm" />
                    <x-button label="Reject All" wire:click="bulkReject" class="btn-error btn-sm" />
                @endif
                <x-button icon="o-x-mark" wire:click="selectedTransactions = []; selectAll = false" class="btn-ghost btn-sm" />
            </div>
        </div>
    @endif
</div>
