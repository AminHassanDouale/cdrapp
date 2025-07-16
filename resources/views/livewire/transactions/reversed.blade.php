<?php
// resources/views/livewire/transactions/reversed.blade.php
// This version works with your existing schema without requiring new columns

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use Toast, WithPagination;

    // Filter properties
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $statusFilter = '';
    public string $amountFrom = '';
    public string $amountTo = '';
    public string $reasonFilter = '';
    public string $channelFilter = '';
    public string $orderBy = 'trans_initate_time';
    public string $orderDirection = 'desc';
    public int $perPage = 50;

    // Modal properties
    public bool $showDetails = false;
    public bool $showReversalForm = false;
    public bool $showBulkReversalForm = false;
    public array $selectedTransaction = [];
    public array $selectedTransactions = [];

    // Reversal form properties
    public string $reversalReason = '';
    public string $reversalNotes = '';
    public string $reversalType = 'full';
    public float $reversalAmount = 0;
    public bool $notifyCustomer = true;
    public string $authorizationCode = '';

    // Constants
    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];
    private const REVERSAL_REASONS = [
        'customer_request' => 'Customer Request',
        'duplicate_transaction' => 'Duplicate Transaction',
        'technical_error' => 'Technical Error',
        'fraud_prevention' => 'Fraud Prevention',
        'merchant_request' => 'Merchant Request',
        'system_error' => 'System Error',
        'compliance_requirement' => 'Compliance Requirement',
        'chargeback' => 'Chargeback',
        'other' => 'Other'
    ];

    public function mount(): void
    {
        // Set default date range to last 30 days
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'statusFilter', 'amountFrom', 'amountTo',
            'reasonFilter', 'channelFilter'
        ]);
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
        $this->success('Filters reset successfully');
    }

    public function viewDetails(string $orderid): void
    {
        $transaction = Transaction::with(['transactionDetails'])
            ->where('orderid', $orderid)
            ->first();

        if ($transaction) {
            $this->selectedTransaction = $transaction->toArray();
            $this->showDetails = true;
        } else {
            $this->error('Transaction not found');
        }
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->selectedTransaction = [];
    }

    public function initiateReversal(string $orderid): void
    {
        $transaction = Transaction::where('orderid', $orderid)->first();

        if (!$transaction) {
            $this->error('Transaction not found');
            return;
        }

        if ($transaction->is_reversed == 1) {
            $this->error('Transaction is already reversed');
            return;
        }

        if ($transaction->trans_status !== 'Completed') {
            $this->error('Only completed transactions can be reversed');
            return;
        }

        $this->selectedTransaction = $transaction->toArray();
        $this->reversalAmount = $transaction->actual_amount;
        $this->resetReversalForm();
        $this->showReversalForm = true;
    }

    public function processReversal(): void
    {
        $this->validate([
            'reversalReason' => 'required|string',
            'reversalNotes' => 'required|string|min:10|max:500',
            'reversalAmount' => 'required|numeric|min:0.01',
            'authorizationCode' => 'required|string|min:4'
        ]);

        try {
            DB::transaction(function () {
                $transaction = Transaction::where('orderid', $this->selectedTransaction['orderid'])->first();

                if (!$transaction) {
                    throw new \Exception('Transaction not found');
                }

                if ($this->reversalAmount > $transaction->actual_amount) {
                    throw new \Exception('Reversal amount cannot exceed original transaction amount');
                }

                // Update transaction using existing fields creatively
                $reversalData = [
                    'reason' => $this->reversalReason,
                    'notes' => $this->reversalNotes,
                    'amount' => $this->reversalAmount,
                    'type' => $this->reversalType,
                    'auth_code' => $this->authorizationCode,
                    'reversed_by' => Auth::user()->name ?? 'System',
                    'reversed_at' => now()->toISOString()
                ];

                $transaction->update([
                    'is_reversed' => 1,
                    'remark' => ($transaction->remark ?? '') . ' | REVERSED: ' . json_encode($reversalData),
                    'last_updated_time' => now(),
                    'checker_id' => Auth::id() ?? 'system'
                ]);

                // Update transaction details with reversal info
                $transactionDetail = $transaction->transactionDetails;
                if ($transactionDetail) {
                    $reversalInfo = "REVERSAL_DATA:" . json_encode($reversalData);
                    $transactionDetail->update([
                        'reserve1' => $this->reversalReason,
                        'reserve2' => $this->authorizationCode,
                        'reserve3' => $this->reversalAmount,
                        'reserve4' => now()->toISOString(),
                        'reserve5' => Auth::user()->name ?? 'System',
                        'remark' => ($transactionDetail->remark ?? '') . ' | ' . $reversalInfo
                    ]);
                }

                // Log in accumulator_reversal field
                $transaction->update([
                    'accumulator_reversal' => json_encode([
                        'reversed_at' => now()->toISOString(),
                        'reversed_by' => Auth::user()->name ?? 'System',
                        'reason' => $this->reversalReason,
                        'amount' => $this->reversalAmount,
                        'notes' => $this->reversalNotes
                    ])
                ]);
            });

            $this->success('Transaction reversal processed successfully');
            $this->closeReversalForm();

            if ($this->notifyCustomer) {
                $this->dispatch('notify-customer-reversal', [
                    'orderid' => $this->selectedTransaction['orderid'],
                    'amount' => $this->reversalAmount
                ]);
            }

        } catch (\Exception $e) {
            $this->error('Reversal failed: ' . $e->getMessage());
        }
    }

    public function closeReversalForm(): void
    {
        $this->showReversalForm = false;
        $this->resetReversalForm();
    }

    private function resetReversalForm(): void
    {
        $this->reversalReason = '';
        $this->reversalNotes = '';
        $this->reversalType = 'full';
        $this->reversalAmount = 0;
        $this->notifyCustomer = true;
        $this->authorizationCode = '';
    }

    public function toggleTransactionSelection(string $orderid): void
    {
        if (in_array($orderid, $this->selectedTransactions)) {
            $this->selectedTransactions = array_diff($this->selectedTransactions, [$orderid]);
        } else {
            $this->selectedTransactions[] = $orderid;
        }
    }

    public function selectAllTransactions(): void
    {
        $transactions = $this->getFilteredTransactions();
        $this->selectedTransactions = $transactions->pluck('orderid')->toArray();
    }

    public function clearSelection(): void
    {
        $this->selectedTransactions = [];
    }

    public function initiateBulkReversal(): void
    {
        if (empty($this->selectedTransactions)) {
            $this->error('Please select transactions to reverse');
            return;
        }

        $this->resetReversalForm();
        $this->showBulkReversalForm = true;
    }

    public function processBulkReversal(): void
    {
        $this->validate([
            'reversalReason' => 'required|string',
            'reversalNotes' => 'required|string|min:10|max:500',
            'authorizationCode' => 'required|string|min:4'
        ]);

        try {
            DB::transaction(function () {
                $processedCount = 0;
                $errors = [];

                foreach ($this->selectedTransactions as $orderid) {
                    try {
                        $transaction = Transaction::where('orderid', $orderid)->first();

                        if (!$transaction) {
                            $errors[] = "Transaction {$orderid} not found";
                            continue;
                        }

                        if ($transaction->is_reversed == 1) {
                            $errors[] = "Transaction {$orderid} already reversed";
                            continue;
                        }

                        if ($transaction->trans_status !== 'Completed') {
                            $errors[] = "Transaction {$orderid} not completed";
                            continue;
                        }

                        // Process bulk reversal
                        $reversalData = [
                            'reason' => $this->reversalReason,
                            'notes' => $this->reversalNotes,
                            'amount' => $transaction->actual_amount,
                            'type' => 'full',
                            'auth_code' => $this->authorizationCode,
                            'reversed_by' => Auth::user()->name ?? 'System',
                            'reversed_at' => now()->toISOString()
                        ];

                        $transaction->update([
                            'is_reversed' => 1,
                            'remark' => ($transaction->remark ?? '') . ' | BULK_REVERSED: ' . json_encode($reversalData),
                            'last_updated_time' => now(),
                            'checker_id' => Auth::id() ?? 'system',
                            'accumulator_reversal' => json_encode($reversalData)
                        ]);

                        $processedCount++;

                    } catch (\Exception $e) {
                        $errors[] = "Transaction {$orderid}: " . $e->getMessage();
                    }
                }

                if ($processedCount > 0) {
                    $this->success("Successfully processed {$processedCount} reversals");
                }

                if (!empty($errors)) {
                    foreach (array_slice($errors, 0, 3) as $error) {
                        $this->warning($error);
                    }
                }
            });

            $this->closeBulkReversalForm();
            $this->clearSelection();

        } catch (\Exception $e) {
            $this->error('Bulk reversal failed: ' . $e->getMessage());
        }
    }

    public function closeBulkReversalForm(): void
    {
        $this->showBulkReversalForm = false;
        $this->resetReversalForm();
    }

    public function exportReversals(): void
    {
        try {
            $transactions = $this->getFilteredTransactions();

            $exportData = [
                'export_type' => 'transaction_reversals',
                'filters' => $this->getActiveFilters(),
                'total_records' => $transactions->count(),
                'reversed_transactions' => $transactions->map(function ($transaction) {
                    $reversalData = $this->extractReversalData($transaction);

                    return [
                        'order_id' => $transaction->orderid,
                        'original_amount' => $transaction->actual_amount,
                        'reversal_amount' => $reversalData['amount'] ?? $transaction->actual_amount,
                        'reversal_reason' => $reversalData['reason'] ?? 'Not specified',
                        'reversal_date' => $reversalData['reversed_at'] ?? $transaction->last_updated_time?->format('Y-m-d H:i:s'),
                        'reversed_by' => $reversalData['reversed_by'] ?? 'Unknown',
                        'status' => $transaction->trans_status,
                        'channel' => $transaction->transactionDetails?->channel ?? 'Unknown',
                        'debit_party' => $transaction->debit_party_mnemonic,
                        'credit_party' => $transaction->credit_party_mnemonic
                    ];
                })->toArray(),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()->name ?? 'System'
            ];

            $this->dispatch('download-reversals-export', $exportData);
            $this->success('Reversals export initiated');
        } catch (\Exception $e) {
            $this->error('Export failed: ' . $e->getMessage());
        }
    }

    private function extractReversalData($transaction): array
    {
        // Try to extract reversal data from accumulator_reversal field
        if (!empty($transaction->accumulator_reversal)) {
            $data = json_decode($transaction->accumulator_reversal, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // Try to extract from remark field
        if (!empty($transaction->remark)) {
            if (preg_match('/REVERSED: ({.*?})/', $transaction->remark, $matches)) {
                $data = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }

        // Try to extract from transaction details reserve fields
        if ($transaction->transactionDetails) {
            $details = $transaction->transactionDetails;
            return [
                'reason' => $details->reserve1 ?? null,
                'auth_code' => $details->reserve2 ?? null,
                'amount' => $details->reserve3 ?? $transaction->actual_amount,
                'reversed_at' => $details->reserve4 ?? null,
                'reversed_by' => $details->reserve5 ?? null
            ];
        }

        return [];
    }

    private function getActiveFilters(): array
    {
        return array_filter([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status_filter' => $this->statusFilter,
            'amount_from' => $this->amountFrom,
            'amount_to' => $this->amountTo,
            'reason_filter' => $this->reasonFilter,
            'channel_filter' => $this->channelFilter
        ]);
    }

    private function getFilteredTransactions()
    {
        $query = Transaction::with(['transactionDetails'])
            ->where('is_reversed', 1);

        // Apply filters
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('orderid', 'LIKE', "%{$this->search}%")
                  ->orWhere('debit_party_mnemonic', 'LIKE', "%{$this->search}%")
                  ->orWhere('credit_party_mnemonic', 'LIKE', "%{$this->search}%");
            });
        }

        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $query->whereBetween('trans_initate_time', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay()
            ]);
        }

        if (!empty($this->statusFilter)) {
            $query->where('trans_status', $this->statusFilter);
        }

        if (!empty($this->amountFrom)) {
            $query->where('actual_amount', '>=', $this->amountFrom);
        }

        if (!empty($this->amountTo)) {
            $query->where('actual_amount', '<=', $this->amountTo);
        }

        if (!empty($this->reasonFilter)) {
            // Search in remark field for reversal reason
            $query->where(function ($q) {
                $q->where('remark', 'LIKE', "%{$this->reasonFilter}%")
                  ->orWhere('accumulator_reversal', 'LIKE', "%{$this->reasonFilter}%")
                  ->orWhereHas('transactionDetails', function ($subQuery) {
                      $subQuery->where('reserve1', $this->reasonFilter);
                  });
            });
        }

        if (!empty($this->channelFilter)) {
            $query->whereHas('transactionDetails', function ($q) {
                $q->where('channel', $this->channelFilter);
            });
        }

        return $query->orderBy($this->orderBy, $this->orderDirection)
                     ->paginate($this->perPage);
    }

    private function getReversalStats(): array
    {
        $baseQuery = Transaction::where('is_reversed', 1);

        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $baseQuery->whereBetween('trans_initate_time', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay()
            ]);
        }

        // Use only existing columns for stats
        $stats = $baseQuery->selectRaw('
            COUNT(*) as total_reversals,
            SUM(actual_amount) as total_reversed_amount,
            AVG(actual_amount) as avg_reversal_amount
        ')->first();

        // Count reasons by searching in remark and accumulator_reversal fields
        $customerRequests = $baseQuery->where(function ($q) {
            $q->where('remark', 'LIKE', '%customer_request%')
              ->orWhere('accumulator_reversal', 'LIKE', '%customer_request%');
        })->count();

        $technicalErrors = $baseQuery->where(function ($q) {
            $q->where('remark', 'LIKE', '%technical_error%')
              ->orWhere('accumulator_reversal', 'LIKE', '%technical_error%');
        })->count();

        $fraudPrevention = $baseQuery->where(function ($q) {
            $q->where('remark', 'LIKE', '%fraud_prevention%')
              ->orWhere('accumulator_reversal', 'LIKE', '%fraud_prevention%');
        })->count();

        return [
            'total_reversals' => $stats->total_reversals ?? 0,
            'total_reversed_amount' => (float)($stats->total_reversed_amount ?? 0),
            'avg_reversal_amount' => (float)($stats->avg_reversal_amount ?? 0),
            'customer_requests' => $customerRequests,
            'technical_errors' => $technicalErrors,
            'fraud_prevention' => $fraudPrevention
        ];
    }

    public function with(): array
    {
        return [
            'transactions' => $this->getFilteredTransactions(),
            'stats' => $this->getReversalStats(),
            'reversalReasons' => collect(self::REVERSAL_REASONS)->map(fn($name, $id) => [
                'id' => $id,
                'name' => $name
            ])->values()->toArray(),
            'statusOptions' => [
                ['id' => 'Completed', 'name' => 'Completed'],
                ['id' => 'Failed', 'name' => 'Failed'],
                ['id' => 'Pending', 'name' => 'Pending']
            ],
            'perPageOptions' => collect(self::PER_PAGE_OPTIONS)->map(fn($option) => [
                'id' => $option,
                'name' => $option . ' per page'
            ])->toArray(),
            'selectedCount' => count($this->selectedTransactions)
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Transaction Reversals" subtitle="Manage and process transaction reversals" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-badge value="Total: {{ number_format($stats['total_reversals']) }}" class="badge-neutral" />
                <x-badge value="Amount: {{ number_format($stats['total_reversed_amount'], 0) }} DJF" class="badge-error" />
                @if($selectedCount > 0)
                <x-badge value="{{ $selectedCount }} Selected" class="badge-primary" />
                @endif
            </div>
        </x-slot:middle>

        <x-slot:actions>
            @if($selectedCount > 0)
            <x-button
                label="Bulk Reverse"
                icon="o-arrow-uturn-left"
                wire:click="initiateBulkReversal"
                class="btn-error btn-sm" />

            <x-button
                label="Clear Selection"
                icon="o-x-mark"
                wire:click="clearSelection"
                class="btn-ghost btn-sm" />
            @endif

            <x-button
                label="Export"
                icon="o-arrow-down-tray"
                wire:click="exportReversals"
                class="btn-outline btn-sm"
                spinner="exportReversals" />

            <x-button
                label="Reset Filters"
                icon="o-arrow-path"
                wire:click="resetFilters"
                class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- SUMMARY CARDS --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        <x-card class="stat-card">
            <x-stat
                title="Total Reversals"
                value="{{ number_format($stats['total_reversals']) }}"
                icon="o-arrow-uturn-left"
                color="text-red-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Reversed Amount"
                value="{{ number_format($stats['total_reversed_amount'], 0) }} DJF"
                icon="o-banknotes"
                color="text-red-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Avg Reversal"
                value="{{ number_format($stats['avg_reversal_amount'], 0) }} DJF"
                icon="o-calculator"
                color="text-orange-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Customer Requests"
                value="{{ number_format($stats['customer_requests']) }}"
                icon="o-user"
                color="text-blue-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Technical Errors"
                value="{{ number_format($stats['technical_errors']) }}"
                icon="o-exclamation-triangle"
                color="text-yellow-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Fraud Prevention"
                value="{{ number_format($stats['fraud_prevention']) }}"
                icon="o-shield-exclamation"
                color="text-purple-500" />
        </x-card>
    </div>

    {{-- FILTERS --}}
    <x-card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Search"
                wire:model.live.debounce.500ms="search"
                placeholder="Order ID, parties..."
                icon="o-magnifying-glass" />

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
                label="Status"
                wire:model.live="statusFilter"
                :options="$statusOptions"
                option-value="id"
                option-label="name"
                placeholder="All Statuses" />
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Amount From"
                wire:model.live.debounce.500ms="amountFrom"
                placeholder="Minimum amount..."
                type="number" />

            <x-input
                label="Amount To"
                wire:model.live.debounce.500ms="amountTo"
                placeholder="Maximum amount..."
                type="number" />

            <x-select
                label="Reversal Reason"
                wire:model.live="reasonFilter"
                :options="$reversalReasons"
                option-value="id"
                option-label="name"
                placeholder="All Reasons" />

            <div class="flex items-center gap-2 pt-6">
                <x-select
                    wire:model.live="perPage"
                    :options="$perPageOptions"
                    option-value="id"
                    option-label="name"
                    class="select-sm" />

                @if($selectedCount === 0)
                <x-button
                    label="Select All"
                    wire:click="selectAllTransactions"
                    class="btn-ghost btn-sm" />
                @endif
            </div>
        </div>
    </x-card>

    {{-- TRANSACTIONS TABLE --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-8">
                            <input type="checkbox" class="checkbox checkbox-sm" />
                        </th>
                        <th>Order ID</th>
                        <th>Parties</th>
                        <th>Amount</th>
                        <th>Reversal Info</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="w-20">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    @php
                        $reversalData = $this->extractReversalData($transaction);
                    @endphp
                    <tr class="hover:bg-base-200">
                        <td>
                            <input type="checkbox" class="checkbox checkbox-sm"
                                   wire:click="toggleTransactionSelection('{{ $transaction->orderid }}')"
                                   @if(in_array($transaction->orderid, $selectedTransactions)) checked @endif />
                        </td>

                        <td>
                            <div class="font-mono text-sm">{{ $transaction->orderid }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $transaction->trans_initate_time->format('Y-m-d H:i') }}
                            </div>
                        </td>

                        <td>
                            <div class="text-sm">
                                <div><strong>From:</strong> {{ $transaction->debit_party_mnemonic }}</div>
                                <div><strong>To:</strong> {{ $transaction->credit_party_mnemonic }}</div>
                            </div>
                        </td>

                        <td>
                            <div class="font-semibold text-red-600">{{ number_format($transaction->actual_amount, 0) }} DJF</div>
                            @if($transaction->fee > 0)
                            <div class="text-xs text-gray-500">Fee: {{ number_format($transaction->fee, 0) }} DJF</div>
                            @endif
                        </td>

                        <td>
                            @if($reversalData['reason'] ?? null)
                            <x-badge
                                value="{{ self::REVERSAL_REASONS[$reversalData['reason']] ?? $reversalData['reason'] }}"
                                class="badge-ghost badge-sm" />
                            @else
                            <span class="text-gray-400">No reason specified</span>
                            @endif

                            @if($reversalData['reversed_by'] ?? null)
                            <div class="text-xs text-gray-500 mt-1">By: {{ $reversalData['reversed_by'] }}</div>
                            @endif
                        </td>

                        <td>
                            @if($reversalData['reversed_at'] ?? null)
                            <div class="text-sm">{{ Carbon::parse($reversalData['reversed_at'])->format('Y-m-d') }}</div>
                            <div class="text-xs text-gray-500">{{ Carbon::parse($reversalData['reversed_at'])->format('H:i:s') }}</div>
                            @else
                            <div class="text-sm">{{ $transaction->last_updated_time?->format('Y-m-d') ?? '—' }}</div>
                            @endif
                        </td>

                        <td>
                            <x-badge
                                value="{{ $transaction->trans_status }}"
                                class="badge-{{ $transaction->trans_status === 'Completed' ? 'success' : ($transaction->trans_status === 'Failed' ? 'error' : 'warning') }} badge-sm" />
                        </td>

                        <td>
                            <div class="flex gap-1">
                                <x-button
                                    icon="o-eye"
                                    wire:click="viewDetails('{{ $transaction->orderid }}')"
                                    class="btn-ghost btn-xs"
                                    tooltip="View Details" />

                                @if($transaction->trans_status === 'Completed' && $transaction->is_reversed != 1)
                                <x-button
                                    icon="o-arrow-uturn-left"
                                    wire:click="initiateReversal('{{ $transaction->orderid }}')"
                                    class="btn-error btn-xs"
                                    tooltip="Reverse Transaction" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            <x-icon name="o-inbox" class="w-8 h-8 mx-auto mb-2" />
                            <div>No reversed transactions found</div>
                            <div class="text-sm mt-2">Transactions marked with is_reversed = 1 will appear here</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    </x-card>

    {{-- TRANSACTION DETAILS MODAL --}}
    <x-modal wire:model="showDetails" title="Transaction Reversal Details" class="backdrop-blur max-w-4xl">
        @if(!empty($selectedTransaction))
        @php
            $reversalData = $this->extractReversalData((object)$selectedTransaction);
        @endphp
        <div class="space-y-6">
            {{-- Transaction Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Order ID</span>
                    </label>
                    <div class="font-mono">{{ $selectedTransaction['orderid'] }}</div>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Transaction Date</span>
                    </label>
                    <div>{{ Carbon::parse($selectedTransaction['trans_initate_time'])->format('Y-m-d H:i:s') }}</div>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Amount</span>
                    </label>
                    <div class="text-lg font-semibold text-red-600">{{ number_format($selectedTransaction['actual_amount'], 0) }} DJF</div>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Status</span>
                    </label>
                    <div>
                        <x-badge value="{{ $selectedTransaction['trans_status'] }}" class="badge-outline" />
                        <x-badge value="REVERSED" class="badge-error ml-2" />
                    </div>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Debit Party</span>
                    </label>
                    <div>{{ $selectedTransaction['debit_party_mnemonic'] }}</div>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Credit Party</span>
                    </label>
                    <div>{{ $selectedTransaction['credit_party_mnemonic'] }}</div>
                </div>
            </div>

            {{-- Reversal Information --}}
            @if(!empty($reversalData))
            <div>
                <label class="label">
                    <span class="label-text font-semibold">Reversal Information</span>
                </label>
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        @if($reversalData['reason'] ?? null)
                        <div><strong>Reason:</strong> {{ self::REVERSAL_REASONS[$reversalData['reason']] ?? $reversalData['reason'] }}</div>
                        @endif
                        @if($reversalData['amount'] ?? null)
                        <div><strong>Reversed Amount:</strong> {{ number_format($reversalData['amount'], 0) }} DJF</div>
                        @endif
                        @if($reversalData['reversed_by'] ?? null)
                        <div><strong>Reversed By:</strong> {{ $reversalData['reversed_by'] }}</div>
                        @endif
                        @if($reversalData['reversed_at'] ?? null)
                        <div><strong>Reversed At:</strong> {{ Carbon::parse($reversalData['reversed_at'])->format('Y-m-d H:i:s') }}</div>
                        @endif
                        @if($reversalData['auth_code'] ?? null)
                        <div><strong>Authorization:</strong> {{ $reversalData['auth_code'] }}</div>
                        @endif
                        @if($reversalData['type'] ?? null)
                        <div><strong>Type:</strong> {{ ucfirst($reversalData['type']) }} Reversal</div>
                        @endif
                    </div>

                    @if($reversalData['notes'] ?? null)
                    <div class="mt-3">
                        <strong>Notes:</strong>
                        <div class="mt-1 text-sm">{{ $reversalData['notes'] }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Transaction Details --}}
            @if(isset($selectedTransaction['transaction_details']))
            <div>
                <label class="label">
                    <span class="label-text font-semibold">Transaction Details</span>
                </label>
                <div class="p-4 bg-base-200 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><strong>Channel:</strong> {{ $selectedTransaction['transaction_details']['channel'] ?? 'Unknown' }}</div>
                        <div><strong>Currency:</strong> {{ $selectedTransaction['currency'] ?? 'DJF' }}</div>
                        <div><strong>Fee:</strong> {{ number_format($selectedTransaction['fee'] ?? 0, 0) }} DJF</div>
                        <div><strong>Commission:</strong> {{ number_format($selectedTransaction['commission'] ?? 0, 0) }}</div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Full Remark Content --}}
            @if($selectedTransaction['remark'] ?? null)
            <div>
                <label class="label">
                    <span class="label-text font-semibold">Transaction Remarks</span>
                </label>
                <div class="p-3 bg-gray-50 border rounded-lg">
                    <pre class="text-sm whitespace-pre-wrap">{{ $selectedTransaction['remark'] }}</pre>
                </div>
            </div>
            @endif
        </div>

        <x-slot:actions>
            @if($selectedTransaction['trans_status'] === 'Completed' && $selectedTransaction['is_reversed'] != 1)
            <x-button
                label="Reverse Transaction"
                wire:click="initiateReversal('{{ $selectedTransaction['orderid'] }}')"
                class="btn-error" />
            @endif
            <x-button label="Close" wire:click="closeDetails" />
        </x-slot:actions>
        @endif
    </x-modal>

    {{-- SINGLE REVERSAL FORM MODAL --}}
    <x-modal wire:model="showReversalForm" title="Process Transaction Reversal" class="backdrop-blur max-w-2xl">
        @if(!empty($selectedTransaction))
        <form wire:submit="processReversal">
            <div class="space-y-4">
                {{-- Transaction Summary --}}
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="font-medium mb-2">Transaction to Reverse:</div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Order ID:</strong> {{ $selectedTransaction['orderid'] }}</div>
                        <div><strong>Amount:</strong> {{ number_format($selectedTransaction['actual_amount'], 0) }} DJF</div>
                        <div><strong>From:</strong> {{ $selectedTransaction['debit_party_mnemonic'] }}</div>
                        <div><strong>To:</strong> {{ $selectedTransaction['credit_party_mnemonic'] }}</div>
                    </div>
                </div>

                {{-- Reversal Form --}}
                <x-input
                    label="Reversal Amount"
                    wire:model="reversalAmount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :max="$selectedTransaction['actual_amount']"
                    required
                    hint="Enter the amount to reverse (max: {{ number_format($selectedTransaction['actual_amount'], 0) }} DJF)" />

                <x-select
                    label="Reversal Reason"
                    wire:model="reversalReason"
                    :options="$reversalReasons"
                    option-value="id"
                    option-label="name"
                    placeholder="Select reason..."
                    required />

                <x-textarea
                    label="Reversal Notes"
                    wire:model="reversalNotes"
                    placeholder="Provide detailed explanation for the reversal (minimum 10 characters)..."
                    rows="3"
                    required />

                <x-input
                    label="Authorization Code"
                    wire:model="authorizationCode"
                    placeholder="Enter authorization code..."
                    required />

                <x-checkbox
                    label="Notify Customer"
                    wire:model="notifyCustomer" />

                {{-- Data Storage Notice --}}
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-information-circle" class="w-5 h-5 text-blue-500 mt-0.5" />
                        <div class="text-sm text-blue-800">
                            <div class="font-medium">Data Storage:</div>
                            <div class="mt-1">Reversal information will be stored in existing fields (remark, accumulator_reversal, and reserve fields) for compatibility with your current schema.</div>
                        </div>
                    </div>
                </div>

                {{-- Warning --}}
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-red-500 mt-0.5" />
                        <div class="text-sm text-red-800">
                            <div class="font-medium">Important:</div>
                            <ul class="mt-1 list-disc list-inside space-y-1">
                                <li>This action will mark the transaction as reversed (is_reversed = 1)</li>
                                <li>Reversal details will be logged in multiple fields for tracking</li>
                                <li>This action cannot be easily undone</li>
                                <li>Ensure you have proper authorization before proceeding</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="closeReversalForm" />
                <x-button label="Process Reversal" type="submit" class="btn-error" spinner="processReversal" />
            </x-slot:actions>
        </form>
        @endif
    </x-modal>

    {{-- BULK REVERSAL FORM MODAL --}}
    <x-modal wire:model="showBulkReversalForm" title="Bulk Transaction Reversal" class="backdrop-blur max-w-2xl">
        <form wire:submit="processBulkReversal">
            <div class="space-y-4">
                {{-- Selection Summary --}}
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="font-medium mb-2">Bulk Reversal Summary:</div>
                    <div class="text-sm">
                        <div><strong>Selected Transactions:</strong> {{ count($selectedTransactions) }}</div>
                        <div class="text-xs text-gray-600 mt-1">
                            All selected transactions will be fully reversed with the same reason and notes.
                        </div>
                    </div>
                </div>

                {{-- Bulk Reversal Form --}}
                <x-select
                    label="Reversal Reason"
                    wire:model="reversalReason"
                    :options="$reversalReasons"
                    option-value="id"
                    option-label="name"
                    placeholder="Select reason..."
                    required />

                <x-textarea
                    label="Reversal Notes"
                    wire:model="reversalNotes"
                    placeholder="Provide detailed explanation for the bulk reversal (minimum 10 characters)..."
                    rows="3"
                    required />

                <x-input
                    label="Authorization Code"
                    wire:model="authorizationCode"
                    placeholder="Enter authorization code..."
                    required />

                <x-checkbox
                    label="Notify Customers"
                    wire:model="notifyCustomer" />

                {{-- Warning --}}
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-red-500 mt-0.5" />
                        <div class="text-sm text-red-800">
                            <div class="font-medium">Bulk Reversal Warning:</div>
                            <ul class="mt-1 list-disc list-inside space-y-1">
                                <li>{{ count($selectedTransactions) }} transactions will be marked as reversed</li>
                                <li>Only eligible transactions (Completed, not already reversed) will be processed</li>
                                <li>Failed reversals will be reported separately</li>
                                <li>Ensure you have proper authorization for bulk operations</li>
                                <li>This action affects multiple customer accounts</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="closeBulkReversalForm" />
                <x-button label="Process Bulk Reversal" type="submit" class="btn-error" spinner="processBulkReversal" />
            </x-slot:actions>
        </form>
    </x-modal>

    {{-- EXPORT SCRIPT --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('download-reversals-export', (data) => {
                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `transaction-reversals-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            // Handle customer notification events
            Livewire.on('notify-customer-reversal', (data) => {
                console.log('Customer notification requested for reversal:', data);
                // You can implement actual notification logic here
                // Example: Send to notification service, email API, SMS gateway, etc.
            });
        });
    </script>
</div>
