<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use App\Models\Transaction;
use App\Models\TransactionDetail;

new class extends Component {
    use Toast;

    public Transaction $transaction;
    public ?TransactionDetail $details;
    public bool $showTechnicalData = false;
    public string $activeTab = 'overview';

    public function mount(string $transaction): void
    {
        $this->transaction = Transaction::findOrFail($transaction);
        $this->details = TransactionDetail::where('orderid', $transaction)->first();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }
}; ?>

<div>
    <x-header title="Transaction Details" subtitle="Order ID: {{ $transaction->orderid }}">
        <x-slot:actions>
            <x-button label="Back to Transaction" icon="o-arrow-left" link="/transactions/{{ $transaction->orderid }}" class="btn-outline" />
            <x-button label="All Transactions" icon="o-queue-list" link="/transactions" class="btn-outline" />
        </x-slot:actions>
    </x-header>

    {{-- Transaction Summary --}}
    <x-card class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">{{ $transaction->orderid }}</h3>
                <p class="text-sm text-gray-600">
                    {{ number_format($transaction->actual_amount, 2) }} {{ $transaction->currency }} •
                    {{ $transaction->trans_initate_time?->format('M d, Y H:i:s') }}
                </p>
            </div>
            <x-badge :value="$transaction->trans_status" class="badge-{{ $transaction->status_color }}" />
        </div>
    </x-card>

    @if($details)
        {{-- Tabs Navigation --}}
        <div class="mb-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px space-x-8">
                    <button wire:click="setActiveTab('overview')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Overview
                    </button>
                    <button wire:click="setActiveTab('process')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'process' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Process Details
                    </button>
                    <button wire:click="setActiveTab('parties')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'parties' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Party Information
                    </button>
                    <button wire:click="setActiveTab('technical')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'technical' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Technical Details
                    </button>
                    @if($details->hasErrors())
                        <button wire:click="setActiveTab('errors')"
                                class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'errors' ? 'border-red-500 text-red-600' : 'border-transparent text-red-500 hover:text-red-700 hover:border-red-300' }}">
                            Errors
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4 ml-1" />
                        </button>
                    @endif
                </nav>
            </div>
        </div>

        {{-- Tab Content --}}
        <div>
            {{-- Overview Tab --}}
            @if($activeTab === 'overview')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-card title="Order Information">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Order State</label>
                                    <p class="mt-1">
                                        <x-badge :value="$details->orderstate" class="badge-{{ $details->order_status_color }}" />
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Business Type</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $details->business_type_name }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Channel</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $details->channel_name }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Transaction Type</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $details->tranactiontype ?: 'N/A' }}
                                    </p>
                                </div>
                            </div>

                            @if($details->processing_time)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Processing Time</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $details->processing_time }} seconds
                                    </p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <x-card title="Timeline">
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-4">
                                    <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full">
                                        <x-icon name="o-play" class="w-4 h-4 text-blue-600" />
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-medium">Created</h4>
                                    <p class="text-sm text-gray-600">{{ $details->createtime?->format('M d, Y H:i:s') }}</p>
                                </div>
                            </div>

                            @if($details->begintime)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mr-4">
                                        <div class="flex items-center justify-center w-8 h-8 bg-yellow-100 rounded-full">
                                            <x-icon name="o-play" class="w-4 h-4 text-yellow-600" />
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">Started</h4>
                                        <p class="text-sm text-gray-600">{{ $details->begintime?->format('M d, Y H:i:s') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if($details->endtime)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mr-4">
                                        <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full">
                                            <x-icon name="o-check" class="w-4 h-4 text-green-600" />
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">Completed</h4>
                                        <p class="text-sm text-gray-600">{{ $details->endtime?->format('M d, Y H:i:s') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if($details->lastupddate)
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mr-4">
                                        <div class="flex items-center justify-center w-8 h-8 bg-purple-100 rounded-full">
                                            <x-icon name="o-arrow-path" class="w-4 h-4 text-purple-600" />
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">Last Updated</h4>
                                        <p class="text-sm text-gray-600">{{ $details->lastupddate?->format('M d, Y H:i:s') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            @endif

            {{-- Process Details Tab --}}
            @if($activeTab === 'process')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-card title="Process Information">
                        <div class="space-y-4">
                            @if($details->procdefid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Process Definition</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->procdefid }}</p>
                                </div>
                            @endif

                            @if($details->procdefversion)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Process Version</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->procdefversion }}</p>
                                </div>
                            @endif

                            @if($details->procinstid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Process Instance ID</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->procinstid }}</p>
                                </div>
                            @endif

                            @if($details->procstate)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Process State</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->procstate }}</p>
                                </div>
                            @endif

                            @if($details->commandid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Command ID</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->commandid }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <x-card title="Service Information">
                        <div class="space-y-4">
                            @if($details->servicecode)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Service Code</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->servicecode }}</p>
                                </div>
                            @endif

                            @if($details->service_index)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Service Index</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->service_index }}</p>
                                </div>
                            @endif

                            @if($details->product_index)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Product Index</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->product_index }}</p>
                                </div>
                            @endif

                            @if($details->businessscope)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Business Scope</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->businessscope }}</p>
                                </div>
                            @endif

                            @if($details->sessionid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Session ID</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->sessionid }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            @endif

            {{-- Party Information Tab --}}
            @if($activeTab === 'parties')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- Initiator --}}
                    <x-card title="Initiator">
                        <div class="space-y-3">
                            @if($details->initiator_id)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">ID</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->initiator_id }}</p>
                                </div>
                            @endif

                            @if($details->initiator_type)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->initiator_type }}</p>
                                </div>
                            @endif

                            @if($details->initiator_mnemonic)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mnemonic</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->initiator_mnemonic }}</p>
                                </div>
                            @endif

                            @if($details->initiator_org_shortcode)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->initiator_org_shortcode }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    {{-- Receiver --}}
                    <x-card title="Receiver">
                        <div class="space-y-3">
                            @if($details->receiver_id)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">ID</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->receiver_id }}</p>
                                </div>
                            @endif

                            @if($details->receiver_type)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->receiver_type }}</p>
                                </div>
                            @endif

                            @if($details->receiver_mnemonic)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mnemonic</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->receiver_mnemonic }}</p>
                                </div>
                            @endif

                            @if($details->receiver_org_shortcode)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->receiver_org_shortcode }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    {{-- Primary Party --}}
                    <x-card title="Primary Party">
                        <div class="space-y-3">
                            @if($details->primary_party_id)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">ID</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->primary_party_id }}</p>
                                </div>
                            @endif

                            @if($details->primary_party_type)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->primary_party_type }}</p>
                                </div>
                            @endif

                            @if($details->primary_mnemonic)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mnemonic</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->primary_mnemonic }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            @endif

            {{-- Technical Details Tab --}}
            @if($activeTab === 'technical')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-card title="Network Information">
                        <div class="space-y-4">
                            @if($details->thirdpartyip)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Third Party IP</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->thirdpartyip }}</p>
                                </div>
                            @endif

                            @if($details->accesspointip)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Access Point IP</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->accesspointip }}</p>
                                </div>
                            @endif

                            @if($details->conversationid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Conversation ID</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->conversationid }}</p>
                                </div>
                            @endif

                            @if($details->origconversationid)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Original Conversation ID</label>
                                    <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $details->origconversationid }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <x-card title="System Information">
                        <div class="space-y-4">
                            @if($details->version)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Version</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->version }}</p>
                                </div>
                            @endif

                            @if($details->languagecode)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Language Code</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->languagecode }}</p>
                                </div>
                            @endif

                            @if($details->eventsource)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Event Source</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->eventsource }}</p>
                                </div>
                            @endif

                            @if($details->load_data_ts)
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Load Data Timestamp</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->load_data_ts }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    {{-- Third Party Information --}}
                    @if($details->isThirdPartyTransaction())
                        <x-card title="Third Party Information" class="lg:col-span-2">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                @if($details->thirdpartyid)
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Third Party ID</label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->thirdpartyid }}</p>
                                    </div>
                                @endif

                                @if($details->thirdpartyreqtime)
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Third Party Request Time</label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->thirdpartyreqtime?->format('M d, Y H:i:s') }}</p>
                                    </div>
                                @endif

                                @if($details->accesspointreqtime)
                                    <div>
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Access Point Request Time</label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $details->accesspointreqtime?->format('M d, Y H:i:s') }}</p>
                                    </div>
                                @endif
                            </div>
                        </x-card>
                    @endif
                </div>
            @endif

            {{-- Errors Tab --}}
            @if($activeTab === 'errors' && $details->hasErrors())
                <x-card title="Error Information">
                    <div class="space-y-4">
                        <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
                            <div class="flex">
                                <x-icon name="o-exclamation-triangle" class="flex-shrink-0 w-5 h-5 mr-3 text-red-400" />
                                <div>
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        Error Code: {{ $details->errorcode }}
                                    </h3>
                                    @if($details->errormessage)
                                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                            <p>{{ $details->errormessage }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($details->errorstack)
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Error Stack</label>
                                <div class="p-4 mt-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                                    <pre class="overflow-x-auto text-xs text-gray-700 dark:text-gray-300">{{ $details->errorstack }}</pre>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>
            @endif
        </div>
    @else
        {{-- No Details Found --}}
        <x-card>
            <div class="py-8 text-center">
                <x-icon name="o-document-magnifying-glass" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
                <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-gray-100">No Transaction Details Found</h3>
                <p class="text-gray-500 dark:text-gray-400">
                    Detailed transaction information is not available for this transaction.
                </p>
            </div>
        </x-card>
    @endif
</div>
