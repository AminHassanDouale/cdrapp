<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use Toast;

    public Transaction $transaction;
    public bool $showRawData = false;

    public function mount($orderid = null): void
    {
        // Get orderid from route parameter if not passed directly
        if (!$orderid) {
            $orderid = request()->route('orderid');
        }

        // Convert to string if it's numeric
        $orderid = (string) $orderid;

        // Log the transaction detail view access
        Log::info('Transaction detail viewed', [
            'user_id' => auth()->id(),
            'orderid' => $orderid,
            'timestamp' => now(),
        ]);

        // Find transaction by orderid
        $this->transaction = Transaction::with([
                'transactionDetails',
                'debitPartyCustomer',
                'creditPartyCustomer',
                'debitPartyOrganization',
                'creditPartyOrganization'
            ])
            ->where('orderid', $orderid)
            ->firstOrFail();
    }

    public function flagSuspicious(): void
    {
        if (!auth()->user()->can('transactions.view')) {
            $this->error('Unauthorized action');
            return;
        }

        // Log the suspicious flag action
        Log::warning('Transaction flagged as suspicious', [
            'user_id' => auth()->id(),
            'orderid' => $this->transaction->orderid,
            'flagged_by' => auth()->user()->name,
            'timestamp' => now(),
        ]);

        // Add logic to flag transaction as suspicious
        $this->success('Transaction flagged for review');
    }

    public function authorizeTransaction(): void
    {
        if (!auth()->user()->can('transactions.process')) {
            $this->error('Unauthorized action');
            return;
        }

        if ($this->transaction->trans_status !== 'Pending') {
            $this->error('Only pending transactions can be authorized');
            return;
        }

        // Log the authorization action
        Log::info('Transaction authorization attempted', [
            'user_id' => auth()->id(),
            'orderid' => $this->transaction->orderid,
            'authorized_by' => auth()->user()->name,
            'previous_status' => $this->transaction->trans_status,
            'timestamp' => now(),
        ]);

        // Add authorization logic here
        $this->success('Transaction authorized successfully');
    }

    public function reverseTransaction(): void
    {
        if (!auth()->user()->can('transactions.reverse')) {
            $this->error('Unauthorized action');
            return;
        }

        if (!$this->transaction->isReversible()) {
            $this->error('This transaction cannot be reversed');
            return;
        }

        // Log the reversal action
        Log::warning('Transaction reversal initiated', [
            'user_id' => auth()->id(),
            'orderid' => $this->transaction->orderid,
            'reversed_by' => auth()->user()->name,
            'amount' => $this->transaction->actual_amount,
            'currency' => $this->transaction->currency,
            'original_status' => $this->transaction->trans_status,
            'timestamp' => now(),
        ]);

        // Add reversal logic here
        $this->success('Transaction reversal initiated');
    }
}; ?>

<div>
    <x-header title="Transaction Details" subtitle="Order ID: {{ $transaction->orderid }}">
        <x-slot:actions>
            <x-button label="Back to Transactions" icon="o-arrow-left" link="/transactions" class="btn-outline" />

            @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                @if($transaction->trans_status === 'Pending')
                    <x-button label="Authorize" icon="o-check" wire:click="authorizeTransaction" class="btn-success" />
                @endif

                @if($transaction->isReversible())
                    <x-button label="Reverse" icon="o-arrow-uturn-left" wire:click="reverseTransaction"
                              class="btn-error"
                              wire:confirm="Are you sure you want to reverse this transaction?" />
                @endif

                <x-button label="Flag Suspicious" icon="o-flag" wire:click="flagSuspicious" class="btn-warning" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Transaction Overview --}}
        <x-card title="Transaction Overview" class="lg:col-span-2">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-stat
                        title="Status"
                        :value="$transaction->trans_status"
                        :description="$transaction->trans_initate_time?->diffForHumans()"
                        icon="o-information-circle"
                        :color="'text-' . $transaction->status_color . '-500'" />
                </div>

                <div>
                    <x-stat
                        title="Amount"
                        :value="number_format($transaction->actual_amount, 2) . ' ' . $transaction->currency"
                        :description="$transaction->fee > 0 ? 'Fee: ' . number_format($transaction->fee, 2) : 'No fee'"
                        icon="o-banknotes"
                        color="text-green-500" />
                </div>

                <div>
                    <x-stat
                        title="Type"
                        :value="$transaction->debit_party_type === $transaction->credit_party_type ? 'Internal' : 'External'"
                        :description="'Party Types: ' . $transaction->debit_party_type . ' → ' . $transaction->credit_party_type"
                        icon="o-arrows-right-left"
                        color="text-blue-500" />
                </div>

                <div>
                    <x-stat
                        title="Processing Time"
                        :value="$transaction->trans_end_time ? $transaction->trans_initate_time?->diffInMinutes($transaction->trans_end_time) . ' min' : 'In Progress'"
                        :description="$transaction->expired_time ? 'Expires: ' . $transaction->expired_time->format('d/m/Y H:i') : 'No expiry'"
                        icon="o-clock"
                        color="text-purple-500" />
                </div>
            </div>
        </x-card>

        {{-- Transaction Flow --}}
        <x-card title="Transaction Flow">
            <div class="space-y-4">
                {{-- Debit Party --}}
                <div class="flex items-center p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <div class="flex-shrink-0 mr-4">
                        <div class="flex items-center justify-center w-10 h-10 bg-red-100 rounded-full dark:bg-red-900">
                            <x-icon name="o-minus" class="w-5 h-5 text-red-600" />
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-red-900 dark:text-red-100">Debit Party</h4>
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $transaction->debit_party_mnemonic }}</p>
                        <p class="text-xs text-red-600 dark:text-red-400">Account: {{ $transaction->debit_party_account }}</p>
                        <p class="text-xs text-red-500 dark:text-red-500">Type: {{ $transaction->debit_account_type }}</p>
                    </div>
                </div>

                {{-- Arrow --}}
                <div class="flex justify-center">
                    <x-icon name="o-arrow-down" class="w-6 h-6 text-gray-400" />
                </div>

                {{-- Transaction Amount --}}
                <div class="flex items-center justify-center p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                            {{ number_format($transaction->actual_amount, 2) }}
                        </div>
                        <div class="text-sm text-blue-600 dark:text-blue-400">{{ $transaction->currency }}</div>
                        @if($transaction->fee > 0)
                            <div class="text-xs text-blue-500 dark:text-blue-500">+ {{ number_format($transaction->fee, 2) }} fee</div>
                        @endif
                    </div>
                </div>

                {{-- Arrow --}}
                <div class="flex justify-center">
                    <x-icon name="o-arrow-down" class="w-6 h-6 text-gray-400" />
                </div>

                {{-- Credit Party --}}
                <div class="flex items-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <div class="flex-shrink-0 mr-4">
                        <div class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-full dark:bg-green-900">
                            <x-icon name="o-plus" class="w-5 h-5 text-green-600" />
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-green-900 dark:text-green-100">Credit Party</h4>
                        <p class="text-sm text-green-700 dark:text-green-300">{{ $transaction->credit_party_mnemonic }}</p>
                        <p class="text-xs text-green-600 dark:text-green-400">Account: {{ $transaction->credit_party_account }}</p>
                        <p class="text-xs text-green-500 dark:text-green-500">Type: {{ $transaction->credit_account_type }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Transaction Timeline --}}
        <x-card title="Transaction Timeline">
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mr-4">
                        <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full dark:bg-blue-900">
                            <x-icon name="o-play" class="w-4 h-4 text-blue-600" />
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium">Transaction Initiated</h4>
                        <p class="text-sm text-gray-600">{{ $transaction->trans_initate_time?->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>

                @if($transaction->last_updated_time)
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div class="flex items-center justify-center w-8 h-8 bg-yellow-100 rounded-full dark:bg-yellow-900">
                                <x-icon name="o-arrow-path" class="w-4 h-4 text-yellow-600" />
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium">Last Updated</h4>
                            <p class="text-sm text-gray-600">{{ $transaction->last_updated_time?->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                @endif

                @if($transaction->trans_end_time && $transaction->trans_end_time !== 'NULL')
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full dark:bg-green-900">
                                <x-icon name="o-check" class="w-4 h-4 text-green-600" />
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium">Transaction Completed</h4>
                            <p class="text-sm text-gray-600">{{ $transaction->trans_end_time }}</p>
                        </div>
                    </div>
                @endif

                @if($transaction->expired_time && $transaction->expired_time->isPast())
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full dark:bg-red-900">
                                <x-icon name="o-x-mark" class="w-4 h-4 text-red-600" />
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium">Transaction Expired</h4>
                            <p class="text-sm text-gray-600">{{ $transaction->expired_time->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Additional Information --}}
        <x-card title="Additional Information" class="lg:col-span-2">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @if($transaction->remark)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Remark</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->remark }}</p>
                    </div>
                @endif

                 @if($transaction->reasonType)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Reason Type</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->reasonType->display_name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $transaction->reason_type }}</p>
                    </div>
                @elseif($transaction->reason_type)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Reason Type ID</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->reason_type }}</p>
                    </div>
                @endif
                @if($transaction->checker_id)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Checker ID</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->checker_id }}</p>
                    </div>
                @endif
                 {{-- Banking Information --}}
        @if($transaction->bank_account_number || $transaction->bank_account_name || $transaction->bank_card_id)
            <x-card title="Banking Information" class="lg:col-span-2">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @if($transaction->bank_account_number)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Account Number</label>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_account_number }}</p>
                        </div>
                    @endif

                    @if($transaction->bank_account_name)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Account Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_account_name }}</p>
                        </div>
                    @endif

                    @if($transaction->bank_card_id)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Card ID</label>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_card_id }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Exchange Rate</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->exchange_rate ?: 'N/A' }}</p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Version</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->version ?: 'N/A' }}</p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">High Value</label>
                    <p class="mt-1 text-sm">
                        @if($transaction->isHighValue())
                            <x-badge value="Yes" class="badge-warning" />
                        @else
                            <x-badge value="No" class="badge-ghost" />
                        @endif
                    </p>
                </div>
                <div>
                     <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Fee</label>
                                           <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->fee ?: 'N/A' }}</p>


                    </p></div><div>
                     <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Commission</label>
                                           <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->commission ?: 'N/A' }}</p>


                    </p></div><div>
                   <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tax</label>

                                           <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->tax ?: 'N/A' }}</p>

                    </p>
                </div>
            </div>
        </x-card>

        {{-- Banking Information --}}
        @if($transaction->bank_account_number || $transaction->bank_account_name || $transaction->bank_card_id)
            <x-card title="Banking Information" class="lg:col-span-2">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @if($transaction->bank_account_number)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Account Number</label>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_account_number }}</p>
                        </div>
                    @endif

                    @if($transaction->bank_account_name)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Account Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_account_name }}</p>
                        </div>
                    @endif

                    @if($transaction->bank_card_id)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank Card ID</label>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $transaction->bank_card_id }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

        {{-- Raw Data Toggle --}}
        <x-card title="Technical Information" class="lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-medium">Raw Transaction Data</h4>
                <x-toggle wire:model.live="showRawData" />
            </div>

            @if($showRawData)
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <pre class="overflow-x-auto text-xs text-gray-700 dark:text-gray-300">{{ json_encode($transaction->toArray(), JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </x-card>

        {{-- Related Transaction Details --}}
        @if($transaction->transactionDetails)
            <x-card title="Transaction Details" class="lg:col-span-2">
                <x-button
                    label="View Full Transaction Details"
                    icon="o-document-magnifying-glass"
                    link="/transactions/{{ $transaction->orderid }}/details"
                    class="btn-outline" />

                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Transaction details include process information, error logs, channel data, and complete audit trail.
                    </p>
                </div>
            </x-card>
        @endif
    </div>
</div>
