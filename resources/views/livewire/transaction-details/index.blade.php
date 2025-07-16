<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Organization;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    use Toast, WithPagination;

    // Filter properties
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $status = '';
    public string $currency = '';
    public string $partyType = '';
    public string $amountMin = '';
    public string $amountMax = '';
    public string $orderBy = 'trans_initate_time';
    public string $orderDirection = 'desc';
    public int $perPage = 25;

    // Modal and detail properties
    public bool $showDetails = false;
    public ?Transaction $selectedTransaction = null;
    public ?TransactionDetail $selectedTransactionDetail = null;

    // Advanced filters
    public bool $showAdvancedFilters = false;
    public string $businessType = '';
    public string $channel = '';
    public bool $reversedOnly = false;
    public bool $highValueOnly = false;

    // Constants for optimization
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const HIGH_VALUE_THRESHOLD = 10000;
    private const MAX_EXPORT_RECORDS = 500; // Reduced from 1000
    private const CACHE_TTL = 1800; // 30 minutes
    private const MAX_DATE_RANGE_DAYS = 90; // Limit date range

    public function mount(): void
    {
        // Set default date range to last 7 days (reduced from 30)
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->validateDateRange();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->validateDateRange();
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    private function validateDateRange(): void
    {
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $start = \Carbon\Carbon::parse($this->dateFrom);
            $end = \Carbon\Carbon::parse($this->dateTo);

            $daysDiff = $start->diffInDays($end);

            if ($daysDiff > self::MAX_DATE_RANGE_DAYS) {
                $this->dateTo = $start->addDays(self::MAX_DATE_RANGE_DAYS)->format('Y-m-d');
                $this->warning('Date range limited to ' . self::MAX_DATE_RANGE_DAYS . ' days for performance');
            }

            if ($this->dateFrom > $this->dateTo) {
                $temp = $this->dateFrom;
                $this->dateFrom = $this->dateTo;
                $this->dateTo = $temp;
            }
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'status', 'currency', 'partyType', 'amountMin', 'amountMax',
            'businessType', 'channel', 'reversedOnly', 'highValueOnly'
        ]);
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
        $this->success('Filters reset successfully');
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = !$this->showAdvancedFilters;
    }

    public function viewDetails(string $orderId): void
    {
        try {
            // Optimized query - only load what we need
            $this->selectedTransaction = Transaction::select([
                'orderid', 'trans_status', 'trans_initate_time', 'trans_end_time',
                'debit_party_id', 'debit_party_type', 'debit_party_mnemonic', 'debit_party_account',
                'credit_party_id', 'credit_party_type', 'credit_party_mnemonic', 'credit_party_account',
                'actual_amount', 'fee', 'currency', 'is_reversed', 'remark'
            ])->where('orderid', $orderId)->first();

            if (!$this->selectedTransaction) {
                $this->error('Transaction not found');
                return;
            }

            // Load transaction details separately
            $this->selectedTransactionDetail = TransactionDetail::select([
                'orderid', 'businesstype', 'channel', 'errorcode', 'errormessage',
                'createtime', 'endtime', 'sessionid', 'conversationid', 'remark'
            ])->where('orderid', $orderId)->first();

            $this->showDetails = true;
        } catch (\Exception $e) {
            \Log::error('Error loading transaction details: ' . $e->getMessage());
            $this->error('Error loading transaction details');
        }
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->selectedTransaction = null;
        $this->selectedTransactionDetail = null;
    }

    public function exportTransactions(): void
    {
        try {
            set_time_limit(120); // Extend time limit for export

            $transactions = $this->getTransactionsQuery()
                ->select([
                    'orderid', 'trans_status', 'trans_initate_time', 'trans_end_time',
                    'actual_amount', 'currency', 'fee', 'debit_party_mnemonic',
                    'credit_party_mnemonic', 'is_reversed', 'remark'
                ])
                ->limit(self::MAX_EXPORT_RECORDS)
                ->get();

            $data = [
                'export_type' => 'transaction_details',
                'filters' => $this->getActiveFilters(),
                'total_records' => $transactions->count(),
                'max_records_note' => 'Limited to ' . self::MAX_EXPORT_RECORDS . ' records for performance',
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'order_id' => $transaction->orderid,
                        'status' => $transaction->trans_status,
                        'initiate_time' => $transaction->trans_initate_time?->format('Y-m-d H:i:s'),
                        'end_time' => $transaction->trans_end_time,
                        'amount' => $transaction->actual_amount,
                        'currency' => $transaction->currency,
                        'fee' => $transaction->fee,
                        'debit_party' => $transaction->debit_party_mnemonic,
                        'credit_party' => $transaction->credit_party_mnemonic,
                        'is_reversed' => $transaction->is_reversed ? 'Yes' : 'No',
                        'remark' => $transaction->remark
                    ];
                }),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()->name ?? 'System'
            ];

            $this->dispatch('download-transactions-export', $data);
            $this->success('Transaction export initiated (max ' . self::MAX_EXPORT_RECORDS . ' records)');
        } catch (\Exception $e) {
            \Log::error('Export failed: ' . $e->getMessage());
            $this->error('Export failed: ' . $e->getMessage());
        }
    }

    private function getActiveFilters(): array
    {
        return array_filter([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status,
            'currency' => $this->currency,
            'party_type' => $this->partyType,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'business_type' => $this->businessType,
            'channel' => $this->channel,
            'reversed_only' => $this->reversedOnly,
            'high_value_only' => $this->highValueOnly
        ]);
    }

    private function getTransactionsQuery(): Builder
    {
        $query = Transaction::select([
            'orderid', 'trans_status', 'trans_initate_time', 'trans_end_time',
            'debit_party_type', 'debit_party_mnemonic', 'credit_party_type', 'credit_party_mnemonic',
            'actual_amount', 'currency', 'fee', 'is_reversed'
        ]);

        // Date range filter (required for performance)
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $query->whereDate('trans_initate_time', '>=', $this->dateFrom)
                  ->whereDate('trans_initate_time', '<=', $this->dateTo);
        } else {
            // Force a date range if none provided
            $query->whereDate('trans_initate_time', '>=', now()->subDays(7));
        }

        // Search filter - optimized for indexed fields
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('orderid', 'like', $this->search . '%') // Use prefix search for better performance
                  ->orWhere('debit_party_mnemonic', 'like', '%' . $this->search . '%')
                  ->orWhere('credit_party_mnemonic', 'like', '%' . $this->search . '%');
            });
        }

        // Status filter
        if (!empty($this->status)) {
            $query->where('trans_status', $this->status);
        }

        // Currency filter
        if (!empty($this->currency)) {
            $query->where('currency', $this->currency);
        }

        // Party type filter
        if (!empty($this->partyType)) {
            $query->where(function ($q) {
                $q->where('debit_party_type', $this->partyType)
                  ->orWhere('credit_party_type', $this->partyType);
            });
        }

        // Amount range filter
        if (!empty($this->amountMin)) {
            $query->where('actual_amount', '>=', $this->amountMin);
        }
        if (!empty($this->amountMax)) {
            $query->where('actual_amount', '<=', $this->amountMax);
        }

        // Advanced filters
        if ($this->reversedOnly) {
            $query->where('is_reversed', 1);
        }

        if ($this->highValueOnly) {
            $query->where('actual_amount', '>=', self::HIGH_VALUE_THRESHOLD);
        }

        // Handle joins more efficiently
        if (!empty($this->businessType) || !empty($this->channel)) {
            $query->join('lbi_ods.t_o_orderhis as td', function ($join) {
                $join->on('lbi_ods.t_o_trans_record.orderid', '=', 'td.orderid');
            });

            if (!empty($this->businessType)) {
                $query->where('td.businesstype', $this->businessType);
            }

            if (!empty($this->channel)) {
                $query->where('td.channel', $this->channel);
            }
        }

        return $query->orderBy($this->orderBy, $this->orderDirection);
    }

    public function with(): array
    {
        try {
            $transactions = $this->getTransactionsQuery()->paginate($this->perPage);

            // Optimized summary calculation
            $summary = $this->getOptimizedSummary();

            // Cached filter options with shorter TTL
            $statusOptions = Cache::remember('transaction_statuses_short', 900, function() {
                return Transaction::distinct()
                    ->whereNotNull('trans_status')
                    ->whereDate('trans_initate_time', '>=', now()->subDays(30))
                    ->pluck('trans_status')
                    ->take(20) // Limit options
                    ->map(fn($status) => ['id' => $status, 'name' => $status])
                    ->toArray();
            });

            $currencyOptions = Cache::remember('transaction_currencies_short', 900, function() {
                return Transaction::distinct()
                    ->whereNotNull('currency')
                    ->whereDate('trans_initate_time', '>=', now()->subDays(30))
                    ->pluck('currency')
                    ->take(10) // Limit options
                    ->map(fn($currency) => ['id' => $currency, 'name' => $currency])
                    ->toArray();
            });

            return [
                'transactions' => $transactions,
                'statusOptions' => $statusOptions,
                'currencyOptions' => $currencyOptions,
                'businessTypeOptions' => $this->getBusinessTypeOptions(),
                'channelOptions' => $this->getChannelOptions(),
                'partyTypeOptions' => [
                    ['id' => '1000', 'name' => 'Customer'],
                    ['id' => '5000', 'name' => 'Organization']
                ],
                'perPageOptions' => collect(self::PER_PAGE_OPTIONS)->map(fn($option) => [
                    'id' => $option,
                    'name' => $option . ' per page'
                ])->toArray(),
                'orderByOptions' => [
                    ['id' => 'trans_initate_time', 'name' => 'Transaction Date'],
                    ['id' => 'actual_amount', 'name' => 'Amount'],
                    ['id' => 'trans_status', 'name' => 'Status'],
                    ['id' => 'orderid', 'name' => 'Order ID']
                ],
                'summary' => $summary,
                'dateRangeLimit' => self::MAX_DATE_RANGE_DAYS
            ];
        } catch (\Exception $e) {
            \Log::error('Error in transaction index: ' . $e->getMessage());
            $this->error('Error loading transactions. Please try with a smaller date range.');

            return [
                'transactions' => collect(),
                'statusOptions' => [],
                'currencyOptions' => [],
                'businessTypeOptions' => [],
                'channelOptions' => [],
                'partyTypeOptions' => [],
                'perPageOptions' => [],
                'orderByOptions' => [],
                'summary' => $this->getEmptySummary(),
                'dateRangeLimit' => self::MAX_DATE_RANGE_DAYS
            ];
        }
    }

    private function getOptimizedSummary(): array
    {
        try {
            // Use a single aggregated query for better performance
            $result = $this->getTransactionsQuery()
                ->selectRaw('
                    COUNT(*) as total_count,
                    SUM(CASE WHEN trans_status = "Completed" THEN 1 ELSE 0 END) as successful_count,
                    SUM(CASE WHEN trans_status = "Failed" THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN trans_status IN ("Pending", "Pending Authorized") THEN 1 ELSE 0 END) as pending_count,
                    SUM(actual_amount) as total_amount,
                    SUM(fee) as total_fees,
                    SUM(CASE WHEN actual_amount >= ? THEN 1 ELSE 0 END) as high_value_count
                ', [self::HIGH_VALUE_THRESHOLD])
                ->first();

            return [
                'total_count' => $result->total_count ?? 0,
                'successful_count' => $result->successful_count ?? 0,
                'failed_count' => $result->failed_count ?? 0,
                'pending_count' => $result->pending_count ?? 0,
                'total_amount' => $result->total_amount ?? 0,
                'total_fees' => $result->total_fees ?? 0,
                'high_value_count' => $result->high_value_count ?? 0
            ];
        } catch (\Exception $e) {
            \Log::error('Error calculating summary: ' . $e->getMessage());
            return $this->getEmptySummary();
        }
    }

    private function getEmptySummary(): array
    {
        return [
            'total_count' => 0,
            'successful_count' => 0,
            'failed_count' => 0,
            'pending_count' => 0,
            'total_amount' => 0,
            'total_fees' => 0,
            'high_value_count' => 0
        ];
    }

    private function getBusinessTypeOptions(): array
    {
        return [
            ['id' => '0', 'name' => 'Standard Transaction'],
            ['id' => '1', 'name' => 'Money Transfer'],
            ['id' => '2', 'name' => 'Bill Payment'],
            ['id' => '3', 'name' => 'Balance Inquiry'],
            ['id' => '4', 'name' => 'Account Management']
        ];
    }

    private function getChannelOptions(): array
    {
        return [
            ['id' => 'USSD', 'name' => 'USSD'],
            ['id' => 'SMS', 'name' => 'SMS'],
            ['id' => 'WEB', 'name' => 'Web Portal'],
            ['id' => 'MOBILE', 'name' => 'Mobile App'],
            ['id' => 'ATM', 'name' => 'ATM'],
            ['id' => 'POS', 'name' => 'Point of Sale'],
            ['id' => 'API', 'name' => 'API']
        ];
    }

    private function getBusinessTypeName(string $type): string
    {
        return match($type) {
            '0' => 'Standard Transaction',
            '1' => 'Money Transfer',
            '2' => 'Bill Payment',
            '3' => 'Balance Inquiry',
            '4' => 'Account Management',
            default => $type
        };
    }

    private function getChannelName(string $channel): string
    {
        return match($channel) {
            'USSD' => 'USSD',
            'SMS' => 'SMS',
            'WEB' => 'Web Portal',
            'MOBILE' => 'Mobile App',
            'ATM' => 'ATM',
            'POS' => 'Point of Sale',
            'API' => 'API',
            default => $channel
        };
    }

    private function getStatusColor(string $status): string
    {
        return match($status) {
            'Completed' => 'success',
            'Authorized' => 'info',
            'Pending', 'Pending Authorized' => 'warning',
            'Failed' => 'error',
            'Cancelled' => 'neutral',
            default => 'neutral'
        };
    }

    private function isHighValue(float $amount): bool
    {
        return $amount >= self::HIGH_VALUE_THRESHOLD;
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Transaction Details" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="Total: {{ number_format($summary['total_count']) }}" class="badge-neutral" />
                <x-badge value="Amount: {{ number_format($summary['total_amount'], 0) }}" class="badge-info" />
                @if($dateRangeLimit)
                <x-badge value="Max {{ $dateRangeLimit }} days" class="badge-warning badge-sm" />
                @endif
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Export"
                icon="o-arrow-down-tray"
                wire:click="exportTransactions"
                class="btn-outline btn-sm"
                spinner="exportTransactions" />

            <x-button
                label="Reset"
                icon="o-x-mark"
                wire:click="resetFilters"
                class="btn-ghost btn-sm" />

            <x-button
                label="{{ $showAdvancedFilters ? 'Hide' : 'Show' }} Advanced"
                icon="o-adjustments-horizontal"
                wire:click="toggleAdvancedFilters"
                class="btn-outline btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- PERFORMANCE WARNING --}}
    @if(!empty($dateFrom) && !empty($dateTo))
        @php
            $daysDiff = \Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo));
        @endphp
        @if($daysDiff > 30)
        <x-alert icon="o-exclamation-triangle" class="alert-warning">
            <span>Large date range ({{ $daysDiff }} days) may affect performance. Consider using a smaller range for faster results.</span>
        </x-alert>
        @endif
    @endif

    {{-- SUMMARY CARDS --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
        <x-card class="stat-card">
            <x-stat
                title="Total"
                value="{{ number_format($summary['total_count']) }}"
                icon="o-chart-bar"
                color="text-blue-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Successful"
                value="{{ number_format($summary['successful_count']) }}"
                icon="o-check-circle"
                color="text-green-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Failed"
                value="{{ number_format($summary['failed_count']) }}"
                icon="o-x-circle"
                color="text-red-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Pending"
                value="{{ number_format($summary['pending_count']) }}"
                icon="o-clock"
                color="text-yellow-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Amount"
                value="{{ number_format($summary['total_amount'], 0) }}"
                icon="o-banknotes"
                color="text-purple-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Fees"
                value="{{ number_format($summary['total_fees'], 0) }}"
                icon="o-currency-dollar"
                color="text-orange-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="High Value"
                value="{{ number_format($summary['high_value_count']) }}"
                icon="o-star"
                color="text-amber-500" />
        </x-card>
    </div>

    {{-- FILTERS --}}
    <x-card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Search Order ID"
                wire:model.live.debounce.500ms="search"
                placeholder="Search by Order ID..."
                icon="o-magnifying-glass"
                hint="Use exact or prefix match for better performance" />

            <x-datepicker
                label="From Date"
                wire:model.live="dateFrom"
                icon="o-calendar"
                :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'maxDate' => 'today']" />

            <x-datepicker
                label="To Date"
                wire:model.live="dateTo"
                icon="o-calendar"
                :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'minDate' => $dateFrom, 'maxDate' => 'today']" />

            <x-select
                label="Status"
                wire:model.live="status"
                :options="$statusOptions"
                placeholder="All Statuses"
                placeholder-value=""
                icon="o-check-circle" />
        </div>

        {{-- Advanced Filters --}}
        @if($showAdvancedFilters)
        <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2 lg:grid-cols-4">
            <x-select
                label="Currency"
                wire:model.live="currency"
                :options="$currencyOptions"
                placeholder="All Currencies"
                placeholder-value=""
                icon="o-currency-euro" />

            <x-select
                label="Party Type"
                wire:model.live="partyType"
                :options="$partyTypeOptions"
                placeholder="All Party Types"
                placeholder-value=""
                icon="o-users" />

            <x-input
                label="Min Amount"
                wire:model.live="amountMin"
                type="number"
                step="0.01"
                icon="o-banknotes" />

            <x-input
                label="Max Amount"
                wire:model.live="amountMax"
                type="number"
                step="0.01"
                icon="o-banknotes" />

            <x-select
                label="Business Type"
                wire:model.live="businessType"
                :options="$businessTypeOptions"
                placeholder="All Types"
                placeholder-value=""
                icon="o-briefcase" />

            <x-select
                label="Channel"
                wire:model.live="channel"
                :options="$channelOptions"
                placeholder="All Channels"
                placeholder-value=""
                icon="o-device-phone-mobile" />

            <div class="flex items-end gap-4">
                <x-checkbox
                    label="Reversed Only"
                    wire:model.live="reversedOnly" />

                <x-checkbox
                    label="High Value (≥{{ number_format(10000) }})"
                    wire:model.live="highValueOnly" />
            </div>

            <x-select
                label="Per Page"
                wire:model.live="perPage"
                :options="$perPageOptions" />
        </div>
        @endif
    </x-card>

    {{-- TRANSACTIONS TABLE --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th class="w-32">Order ID</th>
                        <th class="w-32">Date/Time</th>
                        <th class="w-24">Status</th>
                        <th class="w-32">Debit Party</th>
                        <th class="w-32">Credit Party</th>
                        <th class="w-24">Amount</th>
                        <th class="w-16">Currency</th>
                        <th class="w-20">Fee</th>
                        <th class="w-16">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr class="transaction-row hover">
                        <td>
                            <div class="font-mono text-xs">
                                {{ Str::limit($transaction->orderid, 12) }}
                            </div>
                        </td>
                        <td>
                            <div class="text-xs">
                                {{ $transaction->trans_initate_time?->format('d/m H:i') }}
                            </div>
                        </td>
                        <td>
                            <x-badge
                                value="{{ Str::limit($transaction->trans_status, 8) }}"
                                class="badge-{{ $this->getStatusColor($transaction->trans_status) }} badge-xs" />
                            @if($transaction->is_reversed)
                                <div class="text-xs text-warning mt-1">Rev</div>
                            @endif
                        </td>
                        <td>
                            <div class="text-xs font-medium party-info">
                                {{ Str::limit($transaction->debit_party_mnemonic, 15) }}
                            </div>
                        </td>
                        <td>
                            <div class="text-xs font-medium party-info">
                                {{ Str::limit($transaction->credit_party_mnemonic, 15) }}
                            </div>
                        </td>
                        <td>
                            <div class="font-medium text-xs amount-cell">
                                {{ number_format($transaction->actual_amount, 0) }}
                            </div>
                            @if($this->isHighValue($transaction->actual_amount))
                                <div class="text-xs text-warning">⭐</div>
                            @endif
                        </td>
                        <td class="text-xs">{{ $transaction->currency }}</td>
                        <td class="text-xs">{{ number_format($transaction->fee, 0) }}</td>
                        <td>
                            <x-button
                                icon="o-eye"
                                wire:click="viewDetails('{{ $transaction->orderid }}')"
                                class="btn-ghost btn-xs"
                                spinner="viewDetails" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-8">
                            <div class="text-gray-500">
                                <x-icon name="o-inbox" class="w-8 h-8 mx-auto mb-2" />
                                <p>No transactions found</p>
                                <p class="text-xs">Try adjusting your filters or date range</p>
                            </div>
                            </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    </x-card>

    {{-- TRANSACTION DETAILS MODAL --}}
    <x-modal wire:model="showDetails" title="Transaction Details" class="w-11/12 max-w-4xl">
        @if($selectedTransaction)
        <div class="space-y-4 modal-content">
            {{-- Basic Information --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-card title="Transaction Information" class="h-fit">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Order ID:</span>
                            <span class="font-mono text-sm">{{ $selectedTransaction->orderid }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Status:</span>
                            <x-badge value="{{ $selectedTransaction->trans_status }}"
                                     class="badge-{{ $this->getStatusColor($selectedTransaction->trans_status) }} badge-sm" />
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Amount:</span>
                            <span class="font-medium">{{ number_format($selectedTransaction->actual_amount, 2) }} {{ $selectedTransaction->currency }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Fee:</span>
                            <span>{{ number_format($selectedTransaction->fee, 2) }} {{ $selectedTransaction->currency }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Total:</span>
                            <span class="font-medium">{{ number_format($selectedTransaction->actual_amount + $selectedTransaction->fee, 2) }} {{ $selectedTransaction->currency }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Initiated:</span>
                            <span class="text-sm">{{ $selectedTransaction->trans_initate_time?->format('d/m/Y H:i:s') }}</span>
                        </div>
                        @if($selectedTransaction->trans_end_time && $selectedTransaction->trans_end_time !== 'NULL')
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Completed:</span>
                            <span class="text-sm">{{ \Carbon\Carbon::parse($selectedTransaction->trans_end_time)->format('d/m/Y H:i:s') }}</span>
                        </div>
                        @endif
                        @if($selectedTransaction->is_reversed)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Reversed:</span>
                            <x-badge value="Yes" class="badge-warning badge-sm" />
                        </div>
                        @endif
                        @if($this->isHighValue($selectedTransaction->actual_amount))
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">High Value:</span>
                            <x-badge value="Yes" class="badge-info badge-sm" />
                        </div>
                        @endif
                    </div>
                </x-card>

                <x-card title="Party Information" class="h-fit">
                    <div class="space-y-4">
                        <div>
                            <h6 class="font-medium text-sm text-gray-700 mb-2">Debit Party (From)</h6>
                            <div class="bg-red-50 p-3 rounded border-l-4 border-red-400">
                                <div class="text-sm font-medium">{{ $selectedTransaction->debit_party_mnemonic }}</div>
                                <div class="text-xs text-gray-600 mt-1">
                                    ID: {{ $selectedTransaction->debit_party_id }}
                                    ({{ $selectedTransaction->debit_party_type === '1000' ? 'Customer' : 'Organization' }})
                                </div>
                                <div class="text-xs text-gray-600">Account: {{ $selectedTransaction->debit_party_account }}</div>
                            </div>
                        </div>

                        <div>
                            <h6 class="font-medium text-sm text-gray-700 mb-2">Credit Party (To)</h6>
                            <div class="bg-green-50 p-3 rounded border-l-4 border-green-400">
                                <div class="text-sm font-medium">{{ $selectedTransaction->credit_party_mnemonic }}</div>
                                <div class="text-xs text-gray-600 mt-1">
                                    ID: {{ $selectedTransaction->credit_party_id }}
                                    ({{ $selectedTransaction->credit_party_type === '1000' ? 'Customer' : 'Organization' }})
                                </div>
                                <div class="text-xs text-gray-600">Account: {{ $selectedTransaction->credit_party_account }}</div>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Transaction Details --}}
            @if($selectedTransactionDetail)
            <x-card title="Additional Details">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        @if($selectedTransactionDetail->businesstype)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Business Type:</span>
                            <span class="text-sm">{{ $this->getBusinessTypeName($selectedTransactionDetail->businesstype) }}</span>
                        </div>
                        @endif
                        @if($selectedTransactionDetail->channel)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Channel:</span>
                            <span class="text-sm">{{ $this->getChannelName($selectedTransactionDetail->channel) }}</span>
                        </div>
                        @endif
                        @if($selectedTransactionDetail->sessionid)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Session ID:</span>
                            <span class="font-mono text-xs">{{ $selectedTransactionDetail->sessionid }}</span>
                        </div>
                        @endif
                    </div>
                    <div class="space-y-2">
                        @if($selectedTransactionDetail->conversationid)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Conversation ID:</span>
                            <span class="font-mono text-xs">{{ $selectedTransactionDetail->conversationid }}</span>
                        </div>
                        @endif
                        @if($selectedTransactionDetail->createtime && $selectedTransactionDetail->endtime)
                        @php
                            $processingTime = \Carbon\Carbon::parse($selectedTransactionDetail->createtime)
                                ->diffInSeconds(\Carbon\Carbon::parse($selectedTransactionDetail->endtime));
                        @endphp
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Processing Time:</span>
                            <span class="text-sm">{{ $processingTime }}s</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Remarks --}}
                @if($selectedTransactionDetail->remark || $selectedTransaction->remark)
                <div class="mt-4">
                    <h6 class="font-medium text-sm text-gray-700 mb-2">Remarks</h6>
                    <div class="bg-gray-50 p-3 rounded text-sm">
                        {{ $selectedTransactionDetail->remark ?: $selectedTransaction->remark ?: 'No remarks' }}
                    </div>
                </div>
                @endif

                {{-- Error Information --}}
                @if($selectedTransactionDetail->errorcode && $selectedTransactionDetail->errorcode !== 'NULL')
                <div class="mt-4">
                    <h6 class="font-medium text-sm text-gray-700 mb-2">Error Information</h6>
                    <div class="bg-red-50 p-3 rounded border-l-4 border-red-400">
                        <div class="text-sm font-medium text-red-800">Error Code: {{ $selectedTransactionDetail->errorcode }}</div>
                        @if($selectedTransactionDetail->errormessage && $selectedTransactionDetail->errormessage !== 'NULL')
                        <div class="text-sm text-red-600 mt-1">{{ $selectedTransactionDetail->errormessage }}</div>
                        @endif
                    </div>
                </div>
                @endif
            </x-card>
            @endif

            {{-- Transaction Timeline --}}
            <x-card title="Transaction Timeline">
                <div class="transaction-timeline">
                    <div class="timeline-item">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Transaction Initiated</span>
                            <span class="text-sm text-gray-600">{{ $selectedTransaction->trans_initate_time?->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>

                    @if($selectedTransactionDetail && $selectedTransactionDetail->createtime)
                    <div class="timeline-item">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Order Created</span>
                            <span class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($selectedTransactionDetail->createtime)->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>
                    @endif

                    @if($selectedTransaction->trans_end_time && $selectedTransaction->trans_end_time !== 'NULL')
                    <div class="timeline-item">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Transaction Completed</span>
                            <span class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($selectedTransaction->trans_end_time)->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>
                    @endif

                    @if($selectedTransaction->is_reversed)
                    <div class="timeline-item">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-warning">Transaction Reversed</span>
                            <x-badge value="Reversed" class="badge-warning badge-sm" />
                        </div>
                    </div>
                    @endif
                </div>
            </x-card>
        </div>

        <x-slot:actions>
            <x-button label="Close" wire:click="closeDetails" class="btn-primary" />
        </x-slot:actions>
        @endif
    </x-modal>
</div>

{{-- JavaScript for Export --}}
<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('download-transactions-export', (data) => {
        const dataStr = JSON.stringify(data, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'transactions-export-' + new Date().toISOString().split('T')[0] + '.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });
});
</script>

