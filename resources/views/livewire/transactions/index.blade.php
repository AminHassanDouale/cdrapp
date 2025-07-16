<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\ReasonType;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $currencyFilter = '';
    public string $transactionTypeFilter = '';
    public string $reasonTypeFilter = '';
    public string $accountTypeFilter = '';

    // Updated date properties for range picker
    public string $dateRange = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public string $amountFrom = '';
    public string $amountTo = '';

    // New dedicated party filters
    public string $debitPartyFilter = '';
    public string $creditPartyFilter = '';

    public string $sortBy = 'trans_initate_time';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        // Initialize date range for the range picker
        $this->dateRange = $this->dateFrom . ' to ' . $this->dateTo;
    }

    // Watch for changes in the date range picker
    public function updatedDateRange(): void
    {
        if (!empty($this->dateRange) && str_contains($this->dateRange, ' to ')) {
            $dates = explode(' to ', $this->dateRange);
            if (count($dates) === 2) {
                $this->dateFrom = trim($dates[0]);
                $this->dateTo = trim($dates[1]);
            }
        }
    }

    // Watch for manual changes in individual date fields
    public function updatedDateFrom(): void
    {
        $this->updateDateRange();
    }

    public function updatedDateTo(): void
    {
        $this->updateDateRange();
    }

    private function updateDateRange(): void
    {
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $this->dateRange = $this->dateFrom . ' to ' . $this->dateTo;
        }
    }

    public function with(): array
    {
        $query = Transaction::query();

        // Apply search filter (general search across multiple fields)
        if (!empty($this->search) && trim($this->search) !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $q) use ($searchTerm) {
                // Handle numeric search for orderid
                if (is_numeric($searchTerm)) {
                    $q->where('orderid', $searchTerm);
                } else {
                    // Text-based search
                    $q->where('orderid', 'like', "%{$searchTerm}%")
                      ->orWhere('remark', 'ilike', "%{$searchTerm}%")
                      ->orWhere('debit_party_mnemonic', 'ilike', "%{$searchTerm}%")
                      ->orWhere('credit_party_mnemonic', 'ilike', "%{$searchTerm}%");
                }
            });
        }

        // Apply dedicated debit party filter
        if (!empty($this->debitPartyFilter) && trim($this->debitPartyFilter) !== '') {
            $query->where('debit_party_mnemonic', 'ilike', "%{$this->debitPartyFilter}%");
        }

        // Apply dedicated credit party filter
        if (!empty($this->creditPartyFilter) && trim($this->creditPartyFilter) !== '') {
            $query->where('credit_party_mnemonic', 'ilike', "%{$this->creditPartyFilter}%");
        }

        // Apply status filter
        if (!empty($this->statusFilter) && trim($this->statusFilter) !== '') {
            $query->where('trans_status', $this->statusFilter);
        }

        // Apply currency filter
        if (!empty($this->currencyFilter) && trim($this->currencyFilter) !== '') {
            $query->where('currency', $this->currencyFilter);
        }

        // Apply transaction type filter
        if (!empty($this->transactionTypeFilter) && trim($this->transactionTypeFilter) !== '') {
            $query->whereHas('transactionDetails', function($q) {
                $q->where('tranactiontype', $this->transactionTypeFilter);
            });
        }

        // Apply reason type filter
        if (!empty($this->reasonTypeFilter) && trim($this->reasonTypeFilter) !== '') {
            $query->where('reason_type', $this->reasonTypeFilter);
        }

        // Apply account type filter
        if (!empty($this->accountTypeFilter) && trim($this->accountTypeFilter) !== '') {
            $query->where(function($q) {
                $q->where('debit_account_type', $this->accountTypeFilter)
                  ->orWhere('credit_account_type', $this->accountTypeFilter);
            });
        }

        // Apply date filters
        if (!empty($this->dateFrom) && trim($this->dateFrom) !== '') {
            $query->whereDate('trans_initate_time', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo) && trim($this->dateTo) !== '') {
            $query->whereDate('trans_initate_time', '<=', $this->dateTo);
        }

        // Apply amount filters
        if (!empty($this->amountFrom) && trim($this->amountFrom) !== '' && is_numeric($this->amountFrom)) {
            $query->where('actual_amount', '>=', $this->amountFrom);
        }

        if (!empty($this->amountTo) && trim($this->amountTo) !== '' && is_numeric($this->amountTo)) {
            $query->where('actual_amount', '<=', $this->amountTo);
        }

        $transactions = $query
            ->with(['transactionDetails.transactionType', 'reasonType', 'debitAccountType', 'creditAccountType'])
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $statuses = Transaction::distinct()->pluck('trans_status')->filter()->sort();
        $currencies = Transaction::distinct()->pluck('currency')->filter()->sort();

        // Get unique party mnemonics for autocomplete/dropdown suggestions
        $debitParties = Transaction::distinct()
            ->whereNotNull('debit_party_mnemonic')
            ->where('debit_party_mnemonic', '!=', '')
            ->pluck('debit_party_mnemonic')
            ->filter()
            ->sort()
            ->take(100); // Limit for performance

        $creditParties = Transaction::distinct()
            ->whereNotNull('credit_party_mnemonic')
            ->where('credit_party_mnemonic', '!=', '')
            ->pluck('credit_party_mnemonic')
            ->filter()
            ->sort()
            ->take(100); // Limit for performance

        // Get transaction types - try different approaches
        $transactionTypes = collect();

        try {
            // Load all transaction types (since we see None status in sample data)
            $transactionTypes = TransactionType::orderBy('txn_type_name')
                ->get();

            // Filter out any obviously inactive ones
            $transactionTypes = $transactionTypes->filter(function($type) {
                // Keep if status is not explicitly inactive
                return !in_array(strtolower($type->status ?? ''), ['inactive', 'disabled', 'deleted']);
            });

            $transactionTypes = $transactionTypes->map(fn($type) => [
                'id' => (string) $type->txn_index,
                'name' => $type->alias ?: $type->txn_type_name ?: 'Transaction Type ' . $type->txn_index
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading transaction types: ' . $e->getMessage());
            $transactionTypes = collect();
        }

        // Get reason types - filtered by transaction type if selected
        $reasonTypes = collect();
        if (!empty($this->transactionTypeFilter)) {
            try {
                $reasonTypes = ReasonType::where('txn_index', $this->transactionTypeFilter)
                    ->orderBy('reason_name')
                    ->get();

                // Filter out inactive ones
                $reasonTypes = $reasonTypes->filter(function($reason) {
                    return !in_array(strtolower($reason->status ?? ''), ['inactive', 'disabled', 'deleted']);
                });

                $reasonTypes = $reasonTypes->map(fn($reason) => [
                    'id' => (string) $reason->reason_index,
                    'name' => $reason->alias ?: $reason->reason_name ?: 'Reason ' . $reason->reason_index
                ]);

            } catch (\Exception $e) {
                Log::error('Error loading reason types: ' . $e->getMessage());
                $reasonTypes = collect();
            }
        }

        // Get account types
        $accountTypes = collect();
        try {
            $accountTypes = AccountType::orderBy('account_type_name')
                ->get();

            // Filter out inactive ones
            $accountTypes = $accountTypes->filter(function($accountType) {
                return !in_array(strtolower($accountType->status ?? ''), ['inactive', 'disabled', 'deleted']);
            });

            $accountTypes = $accountTypes->map(fn($accountType) => [
                'id' => (string) $accountType->account_type_id,
                'name' => $accountType->account_type_alias ?: $accountType->account_type_name ?: 'Account Type ' . $accountType->account_type_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading account types: ' . $e->getMessage());
            $accountTypes = collect();
        }

        $stats = [
            'total' => Transaction::count(),
            'today' => Transaction::whereDate('trans_initate_time', today())->count(),
            'pending' => Transaction::pending()->count(),
            'completed' => Transaction::successful()->count(),
            'volume_today' => Transaction::whereDate('trans_initate_time', today())->sum('actual_amount'),
        ];

        return [
            'transactions' => $transactions,
            'statuses' => $statuses,
            'currencies' => $currencies,
            'transactionTypes' => $transactionTypes,
            'reasonTypes' => $reasonTypes,
            'accountTypes' => $accountTypes,
            'debitParties' => $debitParties,
            'creditParties' => $creditParties,
            'stats' => $stats,
        ];
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
        $this->statusFilter = '';
        $this->currencyFilter = '';
        $this->transactionTypeFilter = '';
        $this->reasonTypeFilter = '';
        $this->accountTypeFilter = '';
        $this->debitPartyFilter = '';
        $this->creditPartyFilter = '';
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->dateRange = $this->dateFrom . ' to ' . $this->dateTo;
        $this->amountFrom = '';
        $this->amountTo = '';
        $this->resetPage();
    }

    public function exportTransactions(): void
    {
        $this->info('Transaction export initiated. You will receive a download link shortly.');
    }

    public function performSearch(): void
    {
        // Log the search action
        Log::info('Transaction search performed', [
            'user_id' => auth()->id(),
            'search_term' => $this->search,
            'filters' => [
                'status' => $this->statusFilter,
                'currency' => $this->currencyFilter,
                'transaction_type' => $this->transactionTypeFilter,
                'reason_type' => $this->reasonTypeFilter,
                'account_type' => $this->accountTypeFilter,
                'debit_party' => $this->debitPartyFilter,
                'credit_party' => $this->creditPartyFilter,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'date_range' => $this->dateRange,
                'amount_from' => $this->amountFrom,
                'amount_to' => $this->amountTo,
            ],
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'timestamp' => now(),
        ]);

        // Reset pagination when performing a new search
        $this->resetPage();

        // Show success message
        $this->success('Search completed successfully.');
    }

    public function updatedTransactionTypeFilter(): void
    {
        // Reset reason type when transaction type changes
        $this->reasonTypeFilter = '';

        // Log the transaction type filter change
        Log::info('Transaction type filter changed', [
            'user_id' => auth()->id(),
            'transaction_type_filter' => $this->transactionTypeFilter,
            'timestamp' => now(),
        ]);
    }

    public function debugFilters(): void
    {
        // Debug method to check what data is available
        try {
            // Check if tables exist and have data
            $transactionTypesRaw = \DB::table('lbi_ods.t_o_transaction_type')->get();
            $reasonTypesRaw = \DB::table('lbi_ods.t_o_reason_type')->get();
            $accountTypesRaw = \DB::table('lbi_ods.t_o_account_type')->get();

            Log::debug('Raw Transaction Types from DB:', $transactionTypesRaw->toArray());
            Log::debug('Raw Reason Types from DB:', $reasonTypesRaw->toArray());
            Log::debug('Raw Account Types from DB:', $accountTypesRaw->toArray());

            // Check using models
            $transactionTypesModel = TransactionType::all();
            $reasonTypesModel = ReasonType::all();
            $accountTypesModel = AccountType::all();

            Log::debug('Transaction Types via Model:', $transactionTypesModel->toArray());
            Log::debug('Reason Types via Model:', $reasonTypesModel->toArray());
            Log::debug('Account Types via Model:', $accountTypesModel->toArray());

            // Check active scopes
            $activeTransactionTypes = TransactionType::active()->get();
            $activeReasonTypes = ReasonType::active()->get();

            Log::debug('Active Transaction Types:', $activeTransactionTypes->toArray());
            Log::debug('Active Reason Types:', $activeReasonTypes->toArray());

            // Check current filter values
            Log::debug('Current Filters:', [
                'transactionTypeFilter' => $this->transactionTypeFilter,
                'reasonTypeFilter' => $this->reasonTypeFilter,
                'accountTypeFilter' => $this->accountTypeFilter,
                'debitPartyFilter' => $this->debitPartyFilter,
                'creditPartyFilter' => $this->creditPartyFilter,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'dateRange' => $this->dateRange,
            ]);

            // Check if models exist
            Log::debug('Model Classes:', [
                'TransactionType exists' => class_exists(TransactionType::class),
                'ReasonType exists' => class_exists(ReasonType::class),
                'AccountType exists' => class_exists(AccountType::class),
            ]);

        } catch (\Exception $e) {
            Log::error('Debug Filters Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        $this->info('Debug information logged. Check your application logs for detailed information.');
    }
}; ?>

<div>
    <x-header title="Transactions" subtitle="Manage and monitor all financial transactions">
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-input placeholder="Search transactions..." wire:model="search" clearable icon="o-magnifying-glass" />
                <x-button label="Search" icon="o-magnifying-glass" wire:click="performSearch" class="btn-primary" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Export" icon="o-arrow-down-tray" wire:click="exportTransactions" class="btn-outline" />
            @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                <x-button label="Analytics" icon="o-chart-bar" link="/transactions/analytics" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-5">
        <x-stat
            title="Total Transactions"
            :value="number_format($stats['total'])"
            icon="o-queue-list"
            color="text-blue-500" />

        <x-stat
            title="Today's Transactions"
            :value="number_format($stats['today'])"
            icon="o-calendar-days"
            color="text-green-500" />

        <x-stat
            title="Pending"
            :value="number_format($stats['pending'])"
            icon="o-clock"
            color="text-yellow-500" />

        <x-stat
            title="Completed"
            :value="number_format($stats['completed'])"
            icon="o-check-circle"
            color="text-green-500" />

        <x-stat
            title="Today's Volume"
            :value="number_format($stats['volume_today'], 0) . ' DJF'"
            icon="o-banknotes"
            color="text-purple-500" />
    </div>

    {{-- Filters --}}
    @php
        $dateConfig = ['altFormat' => 'd/m/Y'];
        $dateRangeConfig = ['mode' => 'range', 'altFormat' => 'd/m/Y'];
    @endphp

    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <x-select
                label="Status"
                wire:model="statusFilter"
                :options="$statuses->map(fn($status) => ['id' => $status, 'name' => $status])"
                placeholder="All Statuses" />

            <x-select
                label="Currency"
                wire:model="currencyFilter"
                :options="$currencies->map(fn($currency) => ['id' => $currency, 'name' => $currency])"
                placeholder="All Currencies" />

            <x-select
                label="Transaction Type"
                wire:model.live="transactionTypeFilter"
                :options="$transactionTypes"
                placeholder="All Types ({{ $transactionTypes->count() }} available)" />

            <x-select
                label="Reason Type"
                wire:model="reasonTypeFilter"
                :options="$reasonTypes"
                placeholder="{{ empty($transactionTypeFilter) ? 'Select Transaction Type first' : 'All Reasons (' . $reasonTypes->count() . ' available)' }}"
                :disabled="empty($transactionTypeFilter)" />

            <x-select
                label="Account Type"
                wire:model="accountTypeFilter"
                :options="$accountTypes"
                placeholder="All Account Types ({{ $accountTypes->count() }} available)" />
        </div>

        <!-- Party Filters and Date Range Row -->
        <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Debit Party"
                wire:model.debounce="debitPartyFilter"
                placeholder="Search debit party..."
                icon="o-user-minus"
                clearable />

            <x-input
                label="Credit Party"
                wire:model.debounce="creditPartyFilter"
                placeholder="Search credit party..."
                icon="o-user-plus"
                clearable />

            <!-- Date Range Picker -->
            <x-datepicker
                label="Date Range"
                wire:model.live="dateRange"
                icon="o-calendar"
                :config="$dateRangeConfig"
                hint="Select date range for transactions" />



            <!-- Alternative: Individual Date Fields (if range picker doesn't work) -->

        </div>

        <!-- Amount Filters Row -->
        <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Amount From"
                wire:model.debounce="amountFrom"
                type="number"
                step="0.01"
                placeholder="Minimum amount" />

            <x-input
                label="Amount To"
                wire:model.debounce="amountTo"
                type="number"
                step="0.01"
                placeholder="Maximum amount" />
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <x-button label="Debug Filters" wire:click="debugFilters" class="btn-info btn-sm" />
            <x-button label="Search" icon="o-magnifying-glass" wire:click="performSearch" class="btn-primary" />
            <x-button label="Reset Filters" wire:click="resetFilters" class="btn-ghost" />
        </div>
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
                            <x-button wire:click="sort('trans_status')" class="btn-ghost btn-sm">
                                Status
                                @if($sortBy === 'trans_status')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>
                            <x-button wire:click="sort('trans_initate_time')" class="btn-ghost btn-sm">
                                Date & Time
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
                        <th>Type & Reason</th>
                        <th>Account Types</th>
                        <th>
                            <x-button wire:click="sort('debit_party_mnemonic')" class="btn-ghost btn-sm">
                                Debit Party
                                @if($sortBy === 'debit_party_mnemonic')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>
                            <x-button wire:click="sort('credit_party_mnemonic')" class="btn-ghost btn-sm">
                                Credit Party
                                @if($sortBy === 'credit_party_mnemonic')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </x-button>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr wire:key="transaction-{{ $transaction->orderid }}">
                            <td>
                                @if($transaction->orderid && is_numeric($transaction->orderid))
                                    <x-button
                                        label="{{ $transaction->orderid }}"
                                        link="/transactions/{{ $transaction->orderid }}"
                                        class="font-mono btn-ghost btn-sm" />
                                @else
                                    <span class="font-mono text-sm text-gray-500">{{ $transaction->orderid ?: 'N/A' }}</span>
                                @endif
                            </td>
                            <td>
                                <x-badge
                                    :value="$transaction->trans_status"
                                    class="badge-{{ $transaction->status_color }}" />
                            </td>
                            <td>
                                <div class="text-sm">
                                    {{ $transaction->trans_initate_time?->format('d/m/Y') }}
                                    <br>
                                    <span class="text-gray-500">{{ $transaction->trans_initate_time?->format('H:i:s') }}</span>
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
                                    @if($transaction->transactionDetails && $transaction->transactionDetails->transactionType)
                                        <div class="font-medium text-blue-600">
                                            {{ Str::limit($transaction->transactionDetails->transactionType->display_name, 20) }}
                                        </div>
                                    @endif
                                    @if($transaction->reasonType)
                                        <div class="text-xs text-gray-500">
                                            {{ Str::limit($transaction->reasonType->display_name, 25) }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    @if($transaction->debitAccountType)
                                        <div class="font-medium text-red-600">
                                            <span class="text-xs text-gray-500">Dr:</span>
                                            {{ Str::limit($transaction->debitAccountType->display_name, 15) }}
                                        </div>
                                    @endif
                                    @if($transaction->creditAccountType)
                                        <div class="font-medium text-green-600">
                                            <span class="text-xs text-gray-500">Cr:</span>
                                            {{ Str::limit($transaction->creditAccountType->display_name, 15) }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->debit_party_mnemonic, 25) }}</div>
                                    <div class="text-gray-500">{{ $transaction->debit_party_account }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <div class="font-medium">{{ Str::limit($transaction->credit_party_mnemonic, 25) }}</div>
                                    <div class="text-gray-500">{{ $transaction->credit_party_account }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center space-x-2">
                                    @if($transaction->orderid && is_numeric($transaction->orderid))
                                        <x-button
                                            icon="o-eye"
                                            link="/transactions/{{ $transaction->orderid }}"
                                            class="btn-ghost btn-xs"
                                            tooltip="View Details" />
                                    @else
                                        <span class="text-xs text-gray-400">No details</span>
                                    @endif

                                    @if($transaction->isHighValue())
                                        <x-icon name="o-star" class="w-4 h-4 text-yellow-500" tooltip="High Value" />
                                    @endif

                                    @if($transaction->is_reversed)
                                        <x-icon name="o-arrow-uturn-left" class="w-4 h-4 text-red-500" tooltip="Reversed" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center">
                                <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
                                <p class="text-gray-500">No transactions found</p>
                                @if($search || $statusFilter || $currencyFilter || $transactionTypeFilter || $reasonTypeFilter || $accountTypeFilter || $debitPartyFilter || $creditPartyFilter)
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
