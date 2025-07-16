<?php

use App\Models\Customer;
use App\Models\Transaction;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use WithPagination, Toast;

    public Customer $customer;

    public string $search = '';
    public string $statusFilter = '';
    public string $currencyFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $amountFrom = '';
    public string $amountTo = '';
    public string $transactionTypeFilter = '';

    public string $sortBy = 'trans_initate_time';
    public string $sortDirection = 'desc';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        // Log customer transaction view
        Log::info('Customer transactions viewed', [
            'user_id' => auth()->id(),
            'customer_id' => $customer->customer_id,
            'timestamp' => now(),
        ]);
    }

    public function with(): array
    {
        $query = Transaction::query();

        // Filter transactions for this customer (both debit and credit)
        $query->where(function (Builder $q) {
            $q->where('debit_party_id', $this->customer->customer_id)
              ->orWhere('credit_party_id', $this->customer->customer_id);
        });

        // Apply search filter
        if (!empty($this->search) && trim($this->search) !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $q) use ($searchTerm) {
                if (is_numeric($searchTerm)) {
                    $q->where('orderid', $searchTerm);
                } else {
                    $q->where('orderid', 'like', "%{$searchTerm}%")
                      ->orWhere('remark', 'ilike', "%{$searchTerm}%")
                      ->orWhere('debit_party_mnemonic', 'ilike', "%{$searchTerm}%")
                      ->orWhere('credit_party_mnemonic', 'ilike', "%{$searchTerm}%");
                }
            });
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

        // Get unique values for filters from this customer's transactions
        $baseQuery = Transaction::where(function (Builder $q) {
            $q->where('debit_party_id', $this->customer->customer_id)
              ->orWhere('credit_party_id', $this->customer->customer_id);
        });

        $statuses = $baseQuery->distinct()->pluck('trans_status')->filter()->sort();
        $currencies = $baseQuery->distinct()->pluck('currency')->filter()->sort();

        // Calculate statistics for this customer
        $stats = [
            'total' => $baseQuery->count(),
            'this_month' => $baseQuery->whereMonth('trans_initate_time', now()->month)->count(),
            'completed' => $baseQuery->where('trans_status', 'Completed')->count(),
            'pending' => $baseQuery->whereIn('trans_status', ['Pending', 'Pending Authorized'])->count(),
            'volume_total' => $baseQuery->where('trans_status', 'Completed')->sum('actual_amount'),
            'volume_this_month' => $baseQuery->where('trans_status', 'Completed')
                ->whereMonth('trans_initate_time', now()->month)
                ->sum('actual_amount'),
        ];

        return [
            'transactions' => $transactions,
            'statuses' => $statuses,
            'currencies' => $currencies,
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
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->amountFrom = '';
        $this->amountTo = '';
        $this->resetPage();
    }

    public function exportCustomerTransactions(): void
    {
        $this->info('Customer transaction export initiated.');
    }

    /**
     * Determine if the transaction is incoming or outgoing for this customer
     */
    public function getTransactionDirection($transaction): string
    {
        if ($transaction->debit_party_id == $this->customer->customer_id) {
            return 'outgoing'; // Customer is sending money
        } elseif ($transaction->credit_party_id == $this->customer->customer_id) {
            return 'incoming'; // Customer is receiving money
        }
        return 'unknown';
    }

    /**
     * Get the other party in the transaction
     */
    public function getOtherParty($transaction): string
    {
        if ($transaction->debit_party_id == $this->customer->customer_id) {
            return $transaction->credit_party_mnemonic ?: 'Unknown Recipient';
        } elseif ($transaction->credit_party_id == $this->customer->customer_id) {
            return $transaction->debit_party_mnemonic ?: 'Unknown Sender';
        }
        return 'Unknown Party';
    }
}; ?>

@php
    $dateConfig = ['altFormat' => 'd/m/Y'];
@endphp

<div>
    {{-- HEADER --}}
    <x-header title="Transactions" :subtitle="'Transactions for ' . $customer->user_name">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search transactions..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        <x-slot:actions>
            <x-button label="Back to Customer" icon="o-arrow-left" link="/customers/{{ $customer->customer_id }}" class="btn-outline" />
            <x-button label="Export" icon="o-arrow-down-tray" wire:click="exportCustomerTransactions" class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- CUSTOMER INFO CARD --}}
    <x-card class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <x-icon name="o-user" class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="font-semibold text-lg">{{ $customer->user_name }}</h3>
                    <p class="text-sm text-gray-600">ID: {{ $customer->customer_id }}</p>
                    @if($customer->public_name)
                        <p class="text-sm text-gray-600">{{ $customer->public_name }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <x-badge value="{{ $customer->status }}" class="badge-{{ $customer->status === 'ACTIVE' ? 'success' : 'neutral' }}" />
                <p class="text-sm text-gray-600 mt-1">Trust Level: {{ $customer->trust_level }}</p>
            </div>
        </div>
    </x-card>

    {{-- STATISTICS CARDS --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-6">
        <x-stat
            title="Total Transactions"
            :value="number_format($stats['total'])"
            icon="o-queue-list"
            color="text-blue-500" />

        <x-stat
            title="This Month"
            :value="number_format($stats['this_month'])"
            icon="o-calendar-days"
            color="text-green-500" />

        <x-stat
            title="Completed"
            :value="number_format($stats['completed'])"
            icon="o-check-circle"
            color="text-green-500" />

        <x-stat
            title="Pending"
            :value="number_format($stats['pending'])"
            icon="o-clock"
            color="text-yellow-500" />

        <x-stat
            title="Total Volume"
            :value="number_format($stats['volume_total'], 0) . ' ' . ($currencies->first() ?: 'DJF')"
            icon="o-banknotes"
            color="text-purple-500" />

        <x-stat
            title="This Month Volume"
            :value="number_format($stats['volume_this_month'], 0) . ' ' . ($currencies->first() ?: 'DJF')"
            icon="o-chart-bar"
            color="text-indigo-500" />
    </div>

    {{-- FILTERS --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-select
                label="Status"
                wire:model.live="statusFilter"
                :options="$statuses->map(fn($status) => ['id' => $status, 'name' => $status])"
                placeholder="All Statuses" />

            <x-select
                label="Currency"
                wire:model.live="currencyFilter"
                :options="$currencies->map(fn($currency) => ['id' => $currency, 'name' => $currency])"
                placeholder="All Currencies" />

            <x-datepicker
                label="Date From"
                wire:model="dateFrom"
                icon="o-calendar"
                :config="$dateConfig" />

            <x-datepicker
                label="Date To"
                wire:model="dateTo"
                icon="o-calendar"
                :config="$dateConfig" />
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2 lg:grid-cols-3">
            <x-input
                label="Amount From"
                wire:model.debounce="amountFrom"
                type="number"
                step="0.01" />

            <x-input
                label="Amount To"
                wire:model.debounce="amountTo"
                type="number"
                step="0.01" />
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <x-button label="Reset Filters" wire:click="resetFilters" class="btn-ghost" />
        </div>
    </x-card>

    {{-- TRANSACTIONS TABLE --}}
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
                        <th>Direction</th>
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
                        <th>Other Party</th>
                        <th>Account</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        @php
                            $direction = $this->getTransactionDirection($transaction);
                            $otherParty = $this->getOtherParty($transaction);
                        @endphp
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
                                @if($direction === 'incoming')
                                    <x-badge value="Incoming" class="badge-success" />
                                    <x-icon name="o-arrow-down-left" class="inline w-4 h-4 ml-1 text-green-500" />
                                @elseif($direction === 'outgoing')
                                    <x-badge value="Outgoing" class="badge-error" />
                                    <x-icon name="o-arrow-up-right" class="inline w-4 h-4 ml-1 text-red-500" />
                                @else
                                    <x-badge value="Unknown" class="badge-ghost" />
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
                                <div class="font-medium {{ $direction === 'incoming' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $direction === 'incoming' ? '+' : '-' }}{{ number_format($transaction->actual_amount, 2) }}
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
                                    <div class="font-medium">{{ Str::limit($otherParty, 25) }}</div>
                                    @if($direction === 'incoming')
                                        <div class="text-gray-500">{{ $transaction->debit_party_account }}</div>
                                    @else
                                        <div class="text-gray-500">{{ $transaction->credit_party_account }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    @if($direction === 'incoming')
                                        <div class="text-green-600">{{ $transaction->credit_party_account }}</div>
                                        <div class="text-xs text-gray-500">{{ $transaction->credit_account_type }}</div>
                                    @else
                                        <div class="text-red-600">{{ $transaction->debit_party_account }}</div>
                                        <div class="text-xs text-gray-500">{{ $transaction->debit_account_type }}</div>
                                    @endif
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
                            <td colspan="8" class="py-8 text-center">
                                <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
                                <p class="text-gray-500">No transactions found for this customer</p>
                                @if($search || $statusFilter || $currencyFilter || $transactionTypeFilter)
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
