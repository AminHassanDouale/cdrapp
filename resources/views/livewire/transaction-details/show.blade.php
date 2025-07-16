<?php

use Mary\Traits\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use Toast, WithPagination;

    // Filter properties
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $actionType = '';
    public string $userFilter = '';
    public string $entityType = '';
    public string $entityId = '';
    public string $orderBy = 'timestamp';
    public string $orderDirection = 'desc';
    public int $perPage = 50;

    // Modal properties
    public bool $showDetails = false;
    public array $selectedAuditItem = [];

    // Advanced filters
    public bool $showAdvancedFilters = false;
    public string $ipAddress = '';
    public string $sessionId = '';
    public string $severityLevel = '';
    public bool $systemActionsOnly = false;
    public bool $userActionsOnly = false;

    // Constants
    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];
    private const MAX_DATE_RANGE_DAYS = 90;
    private const CACHE_TTL = 600; // 10 minutes

    // Mock audit data structure - replace with actual audit log model/table
    private array $auditData = [];

    public function mount(): void
    {
        // Set default date range to last 7 days
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        // Initialize mock audit data
        $this->initializeAuditData();
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
            'search', 'actionType', 'userFilter', 'entityType', 'entityId',
            'ipAddress', 'sessionId', 'severityLevel', 'systemActionsOnly', 'userActionsOnly'
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

    public function viewDetails(array $auditItem): void
    {
        $this->selectedAuditItem = $auditItem;
        $this->showDetails = true;
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->selectedAuditItem = [];
    }

    public function exportAuditLog(): void
    {
        try {
            $auditItems = $this->getFilteredAuditData();

            // Limit export to 1000 records for performance
            $exportData = array_slice($auditItems, 0, 1000);

            $data = [
                'export_type' => 'audit_trail',
                'filters' => $this->getActiveFilters(),
                'total_records' => count($exportData),
                'max_records_note' => 'Limited to 1000 records for performance',
                'audit_entries' => $exportData,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()->name ?? 'System'
            ];

            $this->dispatch('download-audit-export', $data);
            $this->success('Audit log export initiated (max 1000 records)');
        } catch (\Exception $e) {
            $this->error('Export failed: ' . $e->getMessage());
        }
    }

    private function getActiveFilters(): array
    {
        return array_filter([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'action_type' => $this->actionType,
            'user_filter' => $this->userFilter,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'ip_address' => $this->ipAddress,
            'session_id' => $this->sessionId,
            'severity_level' => $this->severityLevel,
            'system_actions_only' => $this->systemActionsOnly,
            'user_actions_only' => $this->userActionsOnly
        ]);
    }

    private function initializeAuditData(): void
    {
        // This would be replaced with actual audit log data from your database
        // For now, we'll generate mock data based on transactions

        try {
            $transactions = Transaction::with('transactionDetails')
                ->whereDate('trans_initate_time', '>=', $this->dateFrom)
                ->whereDate('trans_initate_time', '<=', $this->dateTo)
                ->limit(500) // Limit for performance
                ->get();

            $this->auditData = [];

            foreach ($transactions as $transaction) {
                // Transaction creation audit
                $this->auditData[] = [
                    'id' => uniqid(),
                    'timestamp' => $transaction->trans_initate_time,
                    'action_type' => 'transaction_created',
                    'action_name' => 'Transaction Created',
                    'entity_type' => 'transaction',
                    'entity_id' => $transaction->orderid,
                    'user_id' => null,
                    'user_name' => 'System',
                    'user_type' => 'system',
                    'ip_address' => '127.0.0.1',
                    'session_id' => 'sys_' . substr($transaction->orderid, -8),
                    'description' => "Transaction {$transaction->orderid} was created",
                    'old_values' => null,
                    'new_values' => [
                        'order_id' => $transaction->orderid,
                        'amount' => $transaction->actual_amount,
                        'currency' => $transaction->currency,
                        'status' => 'initiated'
                    ],
                    'metadata' => [
                        'debit_party' => $transaction->debit_party_mnemonic,
                        'credit_party' => $transaction->credit_party_mnemonic,
                        'channel' => $transaction->transactionDetails->channel ?? 'unknown'
                    ],
                    'severity_level' => 'info',
                    'risk_level' => $transaction->actual_amount >= 10000 ? 'high' : 'low',
                    'tags' => ['transaction', 'creation', 'system']
                ];

                // Transaction status updates
                if ($transaction->trans_status === 'Completed') {
                    $completedTime = $transaction->trans_end_time ?
                        \Carbon\Carbon::parse($transaction->trans_end_time) :
                        $transaction->trans_initate_time->addMinutes(rand(1, 10));

                    $this->auditData[] = [
                        'id' => uniqid(),
                        'timestamp' => $completedTime,
                        'action_type' => 'transaction_completed',
                        'action_name' => 'Transaction Completed',
                        'entity_type' => 'transaction',
                        'entity_id' => $transaction->orderid,
                        'user_id' => null,
                        'user_name' => 'System',
                        'user_type' => 'system',
                        'ip_address' => '127.0.0.1',
                        'session_id' => 'sys_' . substr($transaction->orderid, -8),
                        'description' => "Transaction {$transaction->orderid} was completed successfully",
                        'old_values' => ['status' => 'pending'],
                        'new_values' => ['status' => 'completed'],
                        'metadata' => [
                            'processing_time' => $transaction->trans_initate_time->diffInSeconds($completedTime) . 's',
                            'final_amount' => $transaction->actual_amount
                        ],
                        'severity_level' => 'info',
                        'risk_level' => 'low',
                        'tags' => ['transaction', 'completion', 'system']
                    ];
                }

                // Failed transactions
                if ($transaction->trans_status === 'Failed') {
                    $failedTime = $transaction->trans_initate_time->addMinutes(rand(1, 5));

                    $this->auditData[] = [
                        'id' => uniqid(),
                        'timestamp' => $failedTime,
                        'action_type' => 'transaction_failed',
                        'action_name' => 'Transaction Failed',
                        'entity_type' => 'transaction',
                        'entity_id' => $transaction->orderid,
                        'user_id' => null,
                        'user_name' => 'System',
                        'user_type' => 'system',
                        'ip_address' => '127.0.0.1',
                        'session_id' => 'sys_' . substr($transaction->orderid, -8),
                        'description' => "Transaction {$transaction->orderid} failed processing",
                        'old_values' => ['status' => 'pending'],
                        'new_values' => ['status' => 'failed'],
                        'metadata' => [
                            'error_code' => $transaction->transactionDetails->errorcode ?? 'ERR_001',
                            'error_message' => $transaction->transactionDetails->errormessage ?? 'Processing failed'
                        ],
                        'severity_level' => 'error',
                        'risk_level' => 'medium',
                        'tags' => ['transaction', 'failure', 'error']
                    ];
                }

                // Reversed transactions
                if ($transaction->is_reversed) {
                    $reversalTime = $transaction->trans_initate_time->addHours(rand(1, 24));

                    $this->auditData[] = [
                        'id' => uniqid(),
                        'timestamp' => $reversalTime,
                        'action_type' => 'transaction_reversed',
                        'action_name' => 'Transaction Reversed',
                        'entity_type' => 'transaction',
                        'entity_id' => $transaction->orderid,
                        'user_id' => rand(1, 10),
                        'user_name' => 'admin_user_' . rand(1, 5),
                        'user_type' => 'admin',
                        'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                        'session_id' => 'sess_' . substr($transaction->orderid, -8),
                        'description' => "Transaction {$transaction->orderid} was reversed by administrator",
                        'old_values' => ['is_reversed' => false],
                        'new_values' => ['is_reversed' => true],
                        'metadata' => [
                            'reversal_reason' => 'Customer request',
                            'original_amount' => $transaction->actual_amount
                        ],
                        'severity_level' => 'warning',
                        'risk_level' => 'high',
                        'tags' => ['transaction', 'reversal', 'admin_action']
                    ];
                }
            }

            // Add some system events
            $this->addSystemEvents();

            // Add some user login events
            $this->addUserEvents();

            // Sort by timestamp
            usort($this->auditData, function ($a, $b) {
                return $b['timestamp']->timestamp <=> $a['timestamp']->timestamp;
            });

        } catch (\Exception $e) {
            \Log::error('Error initializing audit data: ' . $e->getMessage());
            $this->auditData = [];
        }
    }

    private function addSystemEvents(): void
    {
        $systemEvents = [
            [
                'action_type' => 'system_backup',
                'action_name' => 'System Backup',
                'description' => 'Daily system backup completed successfully',
                'severity_level' => 'info',
                'tags' => ['system', 'backup', 'maintenance']
            ],
            [
                'action_type' => 'database_maintenance',
                'action_name' => 'Database Maintenance',
                'description' => 'Database optimization and cleanup performed',
                'severity_level' => 'info',
                'tags' => ['database', 'maintenance', 'optimization']
            ],
            [
                'action_type' => 'security_scan',
                'action_name' => 'Security Scan',
                'description' => 'Automated security scan completed - no threats detected',
                'severity_level' => 'info',
                'tags' => ['security', 'scan', 'automated']
            ]
        ];

        foreach ($systemEvents as $event) {
            $this->auditData[] = array_merge($event, [
                'id' => uniqid(),
                'timestamp' => now()->subHours(rand(1, 48)),
                'entity_type' => 'system',
                'entity_id' => null,
                'user_id' => null,
                'user_name' => 'System',
                'user_type' => 'system',
                'ip_address' => '127.0.0.1',
                'session_id' => 'system_' . uniqid(),
                'old_values' => null,
                'new_values' => null,
                'metadata' => ['automated' => true],
                'risk_level' => 'low'
            ]);
        }
    }

    private function addUserEvents(): void
    {
        $userEvents = [
            [
                'action_type' => 'user_login',
                'action_name' => 'User Login',
                'description' => 'User successfully logged into the system',
                'severity_level' => 'info',
                'user_name' => 'john.doe',
                'user_type' => 'admin',
                'tags' => ['authentication', 'login', 'success']
            ],
            [
                'action_type' => 'user_logout',
                'action_name' => 'User Logout',
                'description' => 'User logged out of the system',
                'severity_level' => 'info',
                'user_name' => 'jane.smith',
                'user_type' => 'operator',
                'tags' => ['authentication', 'logout']
            ],
            [
                'action_type' => 'failed_login',
                'action_name' => 'Failed Login Attempt',
                'description' => 'Failed login attempt detected',
                'severity_level' => 'warning',
                'user_name' => 'unknown',
                'user_type' => 'unknown',
                'tags' => ['authentication', 'failure', 'security']
            ]
        ];

        foreach ($userEvents as $event) {
            $this->auditData[] = array_merge($event, [
                'id' => uniqid(),
                'timestamp' => now()->subHours(rand(1, 72)),
                'entity_type' => 'user',
                'entity_id' => rand(1, 100),
                'user_id' => rand(1, 50),
                'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                'session_id' => 'sess_' . uniqid(),
                'old_values' => null,
                'new_values' => null,
                'metadata' => ['browser' => 'Chrome', 'device' => 'Desktop'],
                'risk_level' => $event['severity_level'] === 'warning' ? 'medium' : 'low'
            ]);
        }
    }

    private function getFilteredAuditData(): array
    {
        $filtered = $this->auditData;

        // Apply filters
        if (!empty($this->search)) {
            $filtered = array_filter($filtered, function ($item) {
                return stripos($item['description'], $this->search) !== false ||
                       stripos($item['entity_id'], $this->search) !== false ||
                       stripos($item['user_name'], $this->search) !== false;
            });
        }

        if (!empty($this->actionType)) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['action_type'] === $this->actionType;
            });
        }

        if (!empty($this->userFilter)) {
            $filtered = array_filter($filtered, function ($item) {
                return stripos($item['user_name'], $this->userFilter) !== false;
            });
        }

        if (!empty($this->entityType)) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['entity_type'] === $this->entityType;
            });
        }

        if (!empty($this->entityId)) {
            $filtered = array_filter($filtered, function ($item) {
                return stripos($item['entity_id'], $this->entityId) !== false;
            });
        }

        if (!empty($this->severityLevel)) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['severity_level'] === $this->severityLevel;
            });
        }

        if ($this->systemActionsOnly) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['user_type'] === 'system';
            });
        }

        if ($this->userActionsOnly) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['user_type'] !== 'system';
            });
        }

        // Apply date filter
        if (!empty($this->dateFrom) && !empty($this->dateTo)) {
            $start = \Carbon\Carbon::parse($this->dateFrom)->startOfDay();
            $end = \Carbon\Carbon::parse($this->dateTo)->endOfDay();

            $filtered = array_filter($filtered, function ($item) use ($start, $end) {
                return $item['timestamp']->between($start, $end);
            });
        }

        return array_values($filtered);
    }

    private function getSummaryStats(): array
    {
        $filtered = $this->getFilteredAuditData();

        $stats = [
            'total_events' => count($filtered),
            'system_events' => 0,
            'user_events' => 0,
            'error_events' => 0,
            'warning_events' => 0,
            'info_events' => 0,
            'high_risk_events' => 0,
            'unique_users' => [],
            'unique_entities' => []
        ];

        foreach ($filtered as $item) {
            if ($item['user_type'] === 'system') {
                $stats['system_events']++;
            } else {
                $stats['user_events']++;
            }

            switch ($item['severity_level']) {
                case 'error':
                    $stats['error_events']++;
                    break;
                case 'warning':
                    $stats['warning_events']++;
                    break;
                case 'info':
                    $stats['info_events']++;
                    break;
            }

            if ($item['risk_level'] === 'high') {
                $stats['high_risk_events']++;
            }

            if ($item['user_name'] && $item['user_name'] !== 'System') {
                $stats['unique_users'][$item['user_name']] = true;
            }

            if ($item['entity_id']) {
                $stats['unique_entities'][$item['entity_id']] = true;
            }
        }

        $stats['unique_users'] = count($stats['unique_users']);
        $stats['unique_entities'] = count($stats['unique_entities']);

        return $stats;
    }

    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
            'success' => 'success',
            default => 'neutral'
        };
    }

    private function getRiskColor(string $risk): string
    {
        return match($risk) {
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'success',
            default => 'neutral'
        };
    }

    private function getUserTypeIcon(string $userType): string
    {
        return match($userType) {
            'system' => 'o-cog-6-tooth',
            'admin' => 'o-shield-check',
            'operator' => 'o-user',
            'customer' => 'o-user-circle',
            default => 'o-question-mark-circle'
        };
    }

    public function with(): array
    {
        $filteredData = $this->getFilteredAuditData();

        // Paginate the filtered data
        $currentPage = $this->getPage();
        $offset = ($currentPage - 1) * $this->perPage;
        $paginatedData = array_slice($filteredData, $offset, $this->perPage);

        // Create a simple pagination object
        $pagination = new \stdClass();
        $pagination->items = $paginatedData;
        $pagination->total = count($filteredData);
        $pagination->currentPage = $currentPage;
        $pagination->perPage = $this->perPage;
        $pagination->lastPage = ceil($pagination->total / $this->perPage);
        $pagination->hasPages = $pagination->lastPage > 1;

        return [
            'auditItems' => $pagination,
            'summary' => $this->getSummaryStats(),
            'actionTypeOptions' => [
                ['id' => 'transaction_created', 'name' => 'Transaction Created'],
                ['id' => 'transaction_completed', 'name' => 'Transaction Completed'],
                ['id' => 'transaction_failed', 'name' => 'Transaction Failed'],
                ['id' => 'transaction_reversed', 'name' => 'Transaction Reversed'],
                ['id' => 'user_login', 'name' => 'User Login'],
                ['id' => 'user_logout', 'name' => 'User Logout'],
                ['id' => 'failed_login', 'name' => 'Failed Login'],
                ['id' => 'system_backup', 'name' => 'System Backup'],
                ['id' => 'database_maintenance', 'name' => 'Database Maintenance'],
                ['id' => 'security_scan', 'name' => 'Security Scan']
            ],
            'entityTypeOptions' => [
                ['id' => 'transaction', 'name' => 'Transaction'],
                ['id' => 'user', 'name' => 'User'],
                ['id' => 'system', 'name' => 'System'],
                ['id' => 'organization', 'name' => 'Organization'],
                ['id' => 'customer', 'name' => 'Customer']
            ],
            'severityOptions' => [
                ['id' => 'info', 'name' => 'Info'],
                ['id' => 'warning', 'name' => 'Warning'],
                ['id' => 'error', 'name' => 'Error'],
                ['id' => 'success', 'name' => 'Success']
            ],
            'perPageOptions' => collect(self::PER_PAGE_OPTIONS)->map(fn($option) => [
                'id' => $option,
                'name' => $option . ' per page'
            ])->toArray(),
            'dateRangeLimit' => self::MAX_DATE_RANGE_DAYS
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Transaction Audit Trail" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2">
                <x-badge value="Total: {{ number_format($summary['total_events']) }}" class="badge-neutral" />
                <x-badge value="Errors: {{ number_format($summary['error_events']) }}" class="badge-error" />
                <x-badge value="Warnings: {{ number_format($summary['warning_events']) }}" class="badge-warning" />
                @if($dateRangeLimit)
                <x-badge value="Max {{ $dateRangeLimit }} days" class="badge-info badge-sm" />
                @endif
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Export"
                icon="o-arrow-down-tray"
                wire:click="exportAuditLog"
                class="btn-outline btn-sm"
                spinner="exportAuditLog" />

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
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-8">
        <x-card class="stat-card">
            <x-stat
                title="Total Events"
                value="{{ number_format($summary['total_events']) }}"
                icon="o-clipboard-document-list"
                color="text-blue-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="System Events"
                value="{{ number_format($summary['system_events']) }}"
                icon="o-cog-6-tooth"
                color="text-gray-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="User Events"
                value="{{ number_format($summary['user_events']) }}"
                icon="o-users"
                color="text-green-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Errors"
                value="{{ number_format($summary['error_events']) }}"
                icon="o-x-circle"
                color="text-red-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Warnings"
                value="{{ number_format($summary['warning_events']) }}"
                icon="o-exclamation-triangle"
                color="text-yellow-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Info Events"
                value="{{ number_format($summary['info_events']) }}"
                icon="o-information-circle"
                color="text-blue-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="High Risk"
                value="{{ number_format($summary['high_risk_events']) }}"
                icon="o-shield-exclamation"
                color="text-red-500" />
        </x-card>

        <x-card class="stat-card">
            <x-stat
                title="Unique Users"
                value="{{ number_format($summary['unique_users']) }}"
                icon="o-user-group"
                color="text-purple-500" />
        </x-card>
    </div>

    {{-- FILTERS --}}
    <x-card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-input
                label="Search"
                wire:model.live.debounce.500ms="search"
                placeholder="Search description, entity ID, user..."
                icon="o-magnifying-glass" />

            <x-datepicker
                label="From Date"
                wire:model.live="dateFrom"
                icon="o-calendar"
                :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'maxDate' => 'today']" />

            <x-datepicker
                label="To Date"
                wire:model.live="dateTo"
                icon="o-calendar"
                :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'minDate' => $dateFrom,  'maxDate' => 'today']" />

            <x-select
                label="Action Type"
                wire:model.live="actionType"
                :options="$actionTypeOptions"
                option-value="id"
                option-label="name"
                placeholder="All Actions" />
        </div>

        {{-- ADVANCED FILTERS --}}
        @if($showAdvancedFilters)
        <div class="pt-4 mt-6 border-t">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <x-input
                    label="User Filter"
                    wire:model.live.debounce.500ms="userFilter"
                    placeholder="Filter by username..."
                    icon="o-user" />

                <x-select
                    label="Entity Type"
                    wire:model.live="entityType"
                    :options="$entityTypeOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="All Entities" />

                <x-input
                    label="Entity ID"
                    wire:model.live.debounce.500ms="entityId"
                    placeholder="Specific entity ID..."
                    icon="o-identification" />

                <x-select
                    label="Severity Level"
                    wire:model.live="severityLevel"
                    :options="$severityOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="All Severities" />
            </div>

            <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-3">
                <x-input
                    label="IP Address"
                    wire:model.live.debounce.500ms="ipAddress"
                    placeholder="Filter by IP address..."
                    icon="o-globe-alt" />

                <x-input
                    label="Session ID"
                    wire:model.live.debounce.500ms="sessionId"
                    placeholder="Filter by session..."
                    icon="o-key" />

                <div class="flex items-center gap-4 pt-6">
                    <x-checkbox
                        label="System Actions Only"
                        wire:model.live="systemActionsOnly" />
                    <x-checkbox
                        label="User Actions Only"
                        wire:model.live="userActionsOnly" />
                </div>
            </div>
        </div>
        @endif

        {{-- PAGINATION CONTROLS --}}
        <div class="flex items-center justify-between mt-4">
            <div class="flex items-center gap-2">
                <x-select
                    wire:model.live="perPage"
                    :options="$perPageOptions"
                    option-value="id"
                    option-label="name"
                    class="select-sm" />

                <span class="text-sm text-gray-600">
                    Showing {{ number_format(($auditItems->currentPage - 1) * $auditItems->perPage + 1) }} to
                    {{ number_format(min($auditItems->currentPage * $auditItems->perPage, $auditItems->total)) }} of
                    {{ number_format($auditItems->total) }} entries
                </span>
            </div>

            <div class="flex items-center gap-2">
                <x-select
                    label="Sort By"
                    wire:model.live="orderBy"
                    :options="[
                        ['id' => 'timestamp', 'name' => 'Timestamp'],
                        ['id' => 'action_type', 'name' => 'Action Type'],
                        ['id' => 'user_name', 'name' => 'User'],
                        ['id' => 'severity_level', 'name' => 'Severity'],
                        ['id' => 'entity_type', 'name' => 'Entity Type']
                    ]"
                    option-value="id"
                    option-label="name"
                    class="select-sm" />

                <x-button
                    icon="{{ $orderDirection === 'asc' ? 'o-arrow-up' : 'o-arrow-down' }}"
                    wire:click="$set('orderDirection', '{{ $orderDirection === 'asc' ? 'desc' : 'asc' }}')"
                    class="btn-ghost btn-sm" />
            </div>
        </div>
    </x-card>

    {{-- AUDIT TRAIL TABLE --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th class="w-32">Timestamp</th>
                        <th class="w-24">Severity</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th class="w-20">Risk</th>
                        <th class="w-16">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditItems->items as $item)
                    <tr class="hover:bg-base-200">
                        <td class="font-mono text-xs">
                            <div>{{ $item['timestamp']->format('Y-m-d') }}</div>
                            <div class="text-gray-500">{{ $item['timestamp']->format('H:i:s') }}</div>
                        </td>

                        <td>
                            <x-badge
                                value="{{ ucfirst($item['severity_level']) }}"
                                class="badge-{{ $this->getSeverityColor($item['severity_level']) }} badge-sm" />
                        </td>

                        <td>
                            <div class="flex items-center gap-2">
                                <x-icon name="{{ $this->getUserTypeIcon($item['user_type']) }}" class="w-4 h-4" />
                                <span class="font-medium">{{ $item['action_name'] }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $item['action_type'] }}</div>
                        </td>

                        <td>
                            <div class="flex items-center gap-2">
                                @if($item['user_type'] === 'system')
                                    <x-icon name="o-cog-6-tooth" class="w-4 h-4 text-gray-500" />
                                @else
                                    <x-icon name="o-user" class="w-4 h-4 text-blue-500" />
                                @endif
                                <span>{{ $item['user_name'] }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ ucfirst($item['user_type']) }}</div>
                        </td>

                        <td>
                            @if($item['entity_id'])
                            <div class="font-mono text-sm">{{ $item['entity_id'] }}</div>
                            <div class="text-xs text-gray-500">{{ ucfirst($item['entity_type']) }}</div>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="max-w-md">
                            <div class="truncate">{{ $item['description'] }}</div>
                            @if(!empty($item['tags']))
                            <div class="flex gap-1 mt-1">
                                @foreach(array_slice($item['tags'], 0, 3) as $tag)
                                <x-badge value="{{ $tag }}" class="badge-ghost badge-xs" />
                                @endforeach
                            </div>
                            @endif
                        </td>

                        <td>
                            <x-badge
                                value="{{ ucfirst($item['risk_level']) }}"
                                class="badge-{{ $this->getRiskColor($item['risk_level']) }} badge-sm" />
                        </td>

                        <td>
                            <x-button
                                icon="o-eye"
                                wire:click="viewDetails({{ json_encode($item) }})"
                                class="btn-ghost btn-xs"
                                tooltip="View Details" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">
                            <x-icon name="o-inbox" class="w-8 h-8 mx-auto mb-2" />
                            <div>No audit records found for the selected filters</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        @if($auditItems->hasPages)
        <div class="flex justify-center mt-4">
            <div class="join">
                <button
                    wire:click="previousPage"
                    @if($auditItems->currentPage <= 1) disabled @endif
                    class="join-item btn btn-sm">
                    <x-icon name="o-chevron-left" class="w-4 h-4" />
                </button>

                @for($i = max(1, $auditItems->currentPage - 2); $i <= min($auditItems->lastPage, $auditItems->currentPage + 2); $i++)
                <button
                    wire:click="gotoPage({{ $i }})"
                    class="join-item btn btn-sm {{ $i === $auditItems->currentPage ? 'btn-active' : '' }}">
                    {{ $i }}
                </button>
                @endfor

                <button
                    wire:click="nextPage"
                    @if($auditItems->currentPage >= $auditItems->lastPage) disabled @endif
                    class="join-item btn btn-sm">
                    <x-icon name="o-chevron-right" class="w-4 h-4" />
                </button>
            </div>
        </div>
        @endif
    </x-card>

    {{-- DETAILS MODAL --}}
    <x-modal wire:model="showDetails" title="Audit Record Details" class="backdrop-blur">
        @if(!empty($selectedAuditItem))
        <div class="space-y-4">
            {{-- BASIC INFO --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Timestamp</span>
                    </label>
                    <div class="font-mono text-sm">
                        {{ \Carbon\Carbon::parse($selectedAuditItem['timestamp'])->format('Y-m-d H:i:s T') }}
                    </div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Action Type</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <x-badge
                            value="{{ $selectedAuditItem['action_name'] }}"
                            class="badge-outline" />
                        <span class="text-sm text-gray-500">{{ $selectedAuditItem['action_type'] }}</span>
                    </div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">User</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <x-icon name="{{ $this->getUserTypeIcon($selectedAuditItem['user_type']) }}" class="w-4 h-4" />
                        <span>{{ $selectedAuditItem['user_name'] }}</span>
                        <x-badge
                            value="{{ ucfirst($selectedAuditItem['user_type']) }}"
                            class="badge-ghost badge-sm" />
                    </div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Entity</span>
                    </label>
                    <div>
                        @if($selectedAuditItem['entity_id'])
                        <div class="font-mono text-sm">{{ $selectedAuditItem['entity_id'] }}</div>
                        <div class="text-xs text-gray-500">{{ ucfirst($selectedAuditItem['entity_type']) }}</div>
                        @else
                        <span class="text-gray-400">No entity</span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Severity</span>
                    </label>
                    <x-badge
                        value="{{ ucfirst($selectedAuditItem['severity_level']) }}"
                        class="badge-{{ $this->getSeverityColor($selectedAuditItem['severity_level']) }}" />
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Risk Level</span>
                    </label>
                    <x-badge
                        value="{{ ucfirst($selectedAuditItem['risk_level']) }}"
                        class="badge-{{ $this->getRiskColor($selectedAuditItem['risk_level']) }}" />
                </div>
            </div>

            {{-- DESCRIPTION --}}
            <div>
                <label class="label">
                    <span class="font-semibold label-text">Description</span>
                </label>
                <div class="p-3 rounded-lg bg-base-200">
                    {{ $selectedAuditItem['description'] }}
                </div>
            </div>

            {{-- NETWORK INFO --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="label">
                        <span class="font-semibold label-text">IP Address</span>
                    </label>
                    <div class="font-mono text-sm">{{ $selectedAuditItem['ip_address'] }}</div>
                </div>

                <div>
                    <label class="label">
                        <span class="font-semibold label-text">Session ID</span>
                    </label>
                    <div class="font-mono text-sm">{{ $selectedAuditItem['session_id'] }}</div>
                </div>
            </div>

            {{-- TAGS --}}
            @if(!empty($selectedAuditItem['tags']))
            <div>
                <label class="label">
                    <span class="font-semibold label-text">Tags</span>
                </label>
                <div class="flex flex-wrap gap-1">
                    @foreach($selectedAuditItem['tags'] as $tag)
                    <x-badge value="{{ $tag }}" class="badge-ghost badge-sm" />
                    @endforeach
                </div>
            </div>
            @endif

            {{-- CHANGES --}}
            @if($selectedAuditItem['old_values'] || $selectedAuditItem['new_values'])
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @if($selectedAuditItem['old_values'])
                <div>
                    <label class="label">
                        <span class="font-semibold text-red-600 label-text">Previous Values</span>
                    </label>
                    <div class="p-3 border border-red-200 rounded-lg bg-red-50">
                        <pre class="text-sm text-red-800">{{ json_encode($selectedAuditItem['old_values'], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
                @endif

                @if($selectedAuditItem['new_values'])
                <div>
                    <label class="label">
                        <span class="font-semibold text-green-600 label-text">New Values</span>
                    </label>
                    <div class="p-3 border border-green-200 rounded-lg bg-green-50">
                        <pre class="text-sm text-green-800">{{ json_encode($selectedAuditItem['new_values'], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- METADATA --}}
            @if($selectedAuditItem['metadata'])
            <div>
                <label class="label">
                    <span class="font-semibold label-text">Metadata</span>
                </label>
                <div class="p-3 rounded-lg bg-base-200">
                    <pre class="text-sm">{{ json_encode($selectedAuditItem['metadata'], JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="Close" wire:click="closeDetails" />
        </x-slot:actions>
        @endif
    </x-modal>

    {{-- EXPORT JAVASCRIPT --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('download-audit-export', (data) => {
                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `audit-trail-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        });
    </script>
</div>
