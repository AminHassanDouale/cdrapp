<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - CDR Banking' : 'CDR Banking System' }}</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">
    <link rel="mask-icon" href="{{ asset('/favicon.ico') }}" color="#3B82F6">

    {{--  Currency  --}}
    <script type="text/javascript" src="https://cdn.jsdelivr.net/gh/robsontenorio/mary@0.44.2/libs/currency/currency.js"></script>

    {{-- ChartJS --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Flatpickr  --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    {{-- Cropper.js --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />

    {{-- Sortable.js --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.1/Sortable.min.js"></script>

    {{-- TinyMCE  --}}
    <script src="https://cdn.tiny.cloud/1/16eam5yke73excub2z217rcau87xhcbs0pxs4y8wmr5r7z6x/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    {{-- PhotoSwipe --}}
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">

{{-- Mobile Navigation --}}
<x-nav sticky class="lg:hidden">
    <x-slot:brand>
        <div class="flex items-center">
            <svg class="w-8 h-8 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
            </svg>
            <span class="text-xl font-bold text-blue-600">CDR Banking</span>
        </div>
    </x-slot:brand>
    <x-slot:actions>
        <label for="main-drawer" class="mr-3 lg:hidden">
            <x-icon name="o-bars-2" class="cursor-pointer" />
        </label>
    </x-slot:actions>
</x-nav>

<x-main>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">
        {{-- Brand Logo --}}
        <div class="p-5 pt-3">
            <div class="flex items-center">
                <svg class="w-10 h-10 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
                <div>
                    <div class="text-xl font-bold text-blue-600">CDR Banking</div>
                    <div class="text-xs text-gray-500">Banking Management System</div>
                </div>
            </div>
        </div>

        <x-menu activate-by-route>

            {{-- User Profile --}}
            @if($user = auth()->user())
                <x-menu-separator />

                <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                    <x-slot:avatar>
                        <div class="avatar">
                            <div class="w-10 rounded-full">
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" />
                            </div>
                        </div>
                    </x-slot:avatar>
                    <x-slot:actions>
                        {{-- Role Badge --}}
                        <div class="flex flex-col items-end">
                            <span class="badge badge-primary badge-sm">{{ $user->primary_role }}</span>
                        </div>
                        <x-dropdown>
                            <x-slot:trigger>
                                <x-button icon="o-cog-6-tooth" class="btn-circle btn-ghost btn-xs" />
                            </x-slot:trigger>
                            <x-menu-item icon="o-user" label="Profile" link="/profile" />
                            <x-menu-item icon="o-key" label="Security" link="/profile/security" />
                            <x-menu-item icon="o-swatch" label="Toggle theme" @click.stop="$dispatch('mary-toggle-theme')" />
                            <x-menu-separator />
                            <x-menu-item icon="o-power" label="Logout" link="/logout" no-wire-navigate class="text-red-500" />
                        </x-dropdown>
                    </x-slot:actions>
                </x-list-item>
            @endif

            <x-menu-separator />

            {{-- Dashboard (All Users) --}}
            <x-menu-item title="Dashboard" icon="o-chart-pie" link="/" />
            <x-menu-item title="Analytics" icon="o-chart-bar" link="/analytics" />

            {{-- Customers Section --}}
            @if(auth()->user()->hasAnyRole(['customer-service', 'manager', 'admin', 'super-admin', 'kyc-officer', 'financial-analyst', 'auditor']))
                <x-menu-separator />
                <x-menu-sub title="Customers" icon="o-users">
                    <x-menu-item title="All Customers" icon="o-user-group" link="/customers" />
                    @if(auth()->user()->hasRole(['customer-service', 'manager', 'admin', 'super-admin']))
                        <x-menu-item title="Add Customer" icon="o-user-plus" link="/customers/create" />
                    @endif
                    <x-menu-item title="Customer Accounts" icon="o-credit-card" link="/customer-accounts" />
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Customer Segments" icon="o-chart-pie" link="/customers/segments" />
                        <x-menu-item title="Customer Analytics" icon="o-presentation-chart-line" link="/customers/analytics" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- Organizations Section --}}
            @if(auth()->user()->hasAnyRole(['operator', 'customer-service', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-sub title="Organizations" icon="o-building-office">
                    <x-menu-item title="All Organizations" icon="o-building-office-2" link="/organizations" />
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Add Organization" icon="o-plus" link="/organizations/create" />
                        <x-menu-item title="Org Analytics" icon="o-chart-bar-square" link="/organizations/analytics" />
                    @endif
                    <x-menu-item title="Org Accounts" icon="o-banknotes" link="/organization-accounts" />
                </x-menu-sub>
            @endif

            {{-- Transaction Management Section --}}
            @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-separator />
                <x-menu-sub title="Transactions" icon="o-arrows-right-left">
                    <x-menu-item title="All Transactions" icon="o-queue-list" link="/transactions" />
                    <x-menu-item title="Pending Transactions" icon="o-clock" link="/transactions/pending" />
                    <x-menu-item title="Completed Transactions" icon="o-check-circle" link="/transactions/completed" />
                    <x-menu-item title="Failed Transactions" icon="o-x-circle" link="/transactions/failed" />
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="High Value Transactions" icon="o-star" link="/transactions/high-value" />
                        <x-menu-item title="Suspicious Activity" icon="o-eye-slash" link="/transactions/suspicious" />
                        <x-menu-item title="Reversed Transactions" icon="o-arrow-uturn-left" link="/transactions/reversed" />
                    @endif
                    @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin']))
                        <x-menu-item title="Transaction Analytics" icon="o-chart-bar" link="/transactions/analytics" />
                        <x-menu-item title="Volume Analysis" icon="o-presentation-chart-line" link="/transactions/volume-analysis" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- Transaction Details Section --}}
            @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-sub title="Transaction Details" icon="o-document-magnifying-glass">
                    <x-menu-item title="All Transaction Details" icon="o-document-text" link="/transaction-details" />
                    <x-menu-item title="Audit Trail" icon="o-eye" link="/transaction-details/audit-trail" />
                    <x-menu-item title="Error Analysis" icon="o-exclamation-triangle" link="/transaction-details/error-analysis" />
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Performance Metrics" icon="o-chart-pie" link="/transactions/performance-metrics" />
                        <x-menu-item title="Channel Analysis" icon="o-funnel" link="/transactions/channel-analysis" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- Operators Section (Admin+) --}}
            @if(auth()->user()->hasRole(['admin', 'super-admin']))
                <x-menu-sub title="Operators" icon="o-user-circle">
                    <x-menu-item title="All Operators" icon="o-users" link="/operators" />
                    <x-menu-item title="Add Operator" icon="o-user-plus" link="/operators/create" />
                    <x-menu-item title="Performance" icon="o-chart-bar" link="/operators/performance" />
                </x-menu-sub>
            @endif

            {{-- KYC & Compliance Section --}}
            @if(auth()->user()->hasAnyRole(['kyc-officer', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-separator />
                <x-menu-sub title="KYC & Compliance" icon="o-shield-check">
                    <x-menu-item title="KYC Dashboard" icon="o-clipboard-document-check" link="/kyc" />
                    <x-menu-item title="Pending Reviews" icon="o-clock" link="/kyc/pending" />
                    <x-menu-item title="Compliance Overview" icon="o-document-check" link="/compliance/overview" />
                    @if(auth()->user()->hasAnyRole(['kyc-officer', 'manager', 'admin', 'super-admin', 'auditor']))
                        <x-menu-item title="Audit Trail" icon="o-eye" link="/compliance/audit-trail" />
                    @endif
                    <x-menu-item title="KYC Reports" icon="o-document-text" link="/kyc/reports" />
                </x-menu-sub>
            @endif

            {{-- Financial Section --}}
            @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin']))
                <x-menu-sub title="Financial" icon="o-banknotes">
                    <x-menu-item title="Financial Dashboard" icon="o-chart-pie" link="/financial" />
                    <x-menu-item title="Account Management" icon="o-credit-card" link="/accounts" />
                    <x-menu-item title="Balance Analysis" icon="o-scale" link="/balances/distribution" />
                    <x-menu-item title="Financial Transactions" icon="o-arrows-right-left" link="/financial/transactions" />
                    <x-menu-item title="High Value Accounts" icon="o-star" link="/accounts/high-value" />
                </x-menu-sub>
            @endif

            {{-- Risk Management Section --}}
            @if(auth()->user()->hasAnyRole(['financial-analyst', 'kyc-officer', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-sub title="Risk Management" icon="o-exclamation-triangle">
                    <x-menu-item title="Risk Overview" icon="o-shield-exclamation" link="/risk" />
                    <x-menu-item title="Risk Alerts" icon="o-bell-alert" link="/risk/alerts" />
                    <x-menu-item title="High Risk Entities" icon="o-exclamation-circle" link="/risk/high-value-no-kyc" />
                    <x-menu-item title="Dormant Accounts" icon="o-moon" link="/risk/dormant-accounts" />
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Suspicious Activity" icon="o-eye-slash" link="/risk/suspicious-activity" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- Reports & Analytics Section --}}
            @if(auth()->user()->hasAnyRole(['kyc-officer', 'financial-analyst', 'manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-separator />
                <x-menu-sub title="Reports & Analytics" icon="o-document-chart-bar">
                    <x-menu-item title="All Reports" icon="o-folder" link="/reports" />
                    <x-menu-item title="Executive Summary" icon="o-presentation-chart-line" link="/reports/executive-summary" />
                    <x-menu-item title="Compliance Reports" icon="o-document-check" link="/reports/compliance" />
                    <x-menu-item title="Financial Reports" icon="o-chart-bar" link="/reports/financial" />
                    @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin']))
                        <x-menu-item title="Transaction Reports" icon="o-arrows-right-left" link="/transactions/reports" />
                        <x-menu-item title="Daily Summary" icon="o-calendar-days" link="/transactions/daily-summary" />
                        <x-menu-item title="Monthly Report" icon="o-calendar" link="/transactions/monthly-report" />
                    @endif
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Custom Reports" icon="o-cog" link="/reports/custom" />
                        <x-menu-item title="Business Intelligence" icon="o-light-bulb" link="/analytics/business-intelligence" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- User Management Section (Admin+) --}}
            @if(auth()->user()->hasRole(['admin', 'super-admin']))
                <x-menu-separator />
                <x-menu-sub title="User Management" icon="o-user-group">
                    <x-menu-item title="All Users" icon="o-users" link="/users" />
                    <x-menu-item title="Add User" icon="o-user-plus" link="/users/create" />
                    @if(auth()->user()->hasRole('super-admin'))
                        <x-menu-item title="Roles & Permissions" icon="o-key" link="/settings/permissions" />
                    @endif
                </x-menu-sub>
            @endif

            {{-- System Settings (Super Admin) --}}
            @if(auth()->user()->hasRole('super-admin'))
                <x-menu-sub title="System Settings" icon="o-cog-6-tooth">
                    <x-menu-item title="General Settings" icon="o-adjustments-horizontal" link="/settings" />
                    <x-menu-item title="System Configuration" icon="o-server" link="/settings/system" />
                    <x-menu-item title="User Settings" icon="o-user-group" link="/settings/users" />
                </x-menu-sub>
            @endif

            <x-menu-separator />

            {{-- Search (All Users) --}}
            <x-menu-item title="Global Search" @click.stop="$dispatch('mary-search-open')" icon="o-magnifying-glass" badge="Cmd + G" />

            {{-- Advanced Search (Manager+) --}}
            @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin', 'auditor']))
                <x-menu-item title="Advanced Search" icon="o-funnel" link="/search/advanced" />
                @if(auth()->user()->hasAnyRole(['financial-analyst', 'manager', 'admin', 'super-admin']))
                    <x-menu-item title="Advanced Transaction Search" icon="o-magnifying-glass-plus" link="/search/advanced-transactions" />
                @endif
            @endif

            {{-- Quick Export Actions --}}
            @if(auth()->user()->hasAnyRole(['customer-service', 'manager', 'admin', 'super-admin']))
                <x-menu-separator />
                <x-menu-sub title="Quick Exports" icon="o-arrow-down-tray">
                    @if(auth()->user()->hasRole(['customer-service', 'manager', 'admin', 'super-admin']))
                        <x-menu-item title="Export Customers" icon="o-users" link="/export/customers" no-wire-navigate />
                    @endif
                    @if(auth()->user()->hasRole(['manager', 'admin', 'super-admin']))
                        <x-menu-item title="Export Organizations" icon="o-building-office" link="/export/organizations" no-wire-navigate />
                    @endif
                    @if(auth()->user()->hasRole(['financial-analyst', 'manager', 'admin', 'super-admin']))
                        <x-menu-item title="Export Financial Data" icon="o-banknotes" link="/export/financial" no-wire-navigate />
                        <x-menu-item title="Export Transactions" icon="o-arrows-right-left" link="/export/transactions" no-wire-navigate />
                    @endif
                    @if(auth()->user()->hasAnyRole(['kyc-officer', 'manager', 'admin', 'super-admin', 'auditor']))
                        <x-menu-item title="Export Compliance" icon="o-shield-check" link="/export/compliance" no-wire-navigate />
                    @endif
                </x-menu-sub>
            @endif

        </x-menu>
    </x-slot:sidebar>

    {{-- Main Content Area --}}
    <x-slot:content>
        {{-- Role-based Welcome Message --}}
        @if(session('success'))
            <div class="mb-4 alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current shrink-0" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current shrink-0" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Page Content --}}
        {{ $slot }}

        {{-- Footer --}}
        <div class="flex items-center justify-between pt-6 mt-8 border-t border-gray-200">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-500">CDR Banking System v2.1</span>
                <span class="text-sm text-gray-400">•</span>
                <span class="text-sm text-gray-500">© {{ date('Y') }} Banking Management</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">Role:</span>
                <span class="badge badge-primary badge-sm">{{ auth()->user()->primary_role }}</span>
            </div>
        </div>
    </x-slot:content>
</x-main>

{{-- Toast Notifications --}}
<x-toast />

{{-- Enhanced Spotlight Search with Transactions --}}
<x-spotlight
    search-text="Search customers, organizations, accounts, transactions, or any banking records..."
    search-debounce="300"
    :search-results="[
        [
            'name' => 'Customers',
            'items' => [
                ['name' => 'All Customers', 'description' => 'View customer directory', 'icon' => 'o-users', 'link' => '/customers'],
                ['name' => 'Add Customer', 'description' => 'Create new customer', 'icon' => 'o-user-plus', 'link' => '/customers/create'],
                ['name' => 'Customer Analytics', 'description' => 'View customer insights', 'icon' => 'o-chart-bar', 'link' => '/customers/analytics'],
            ]
        ],
        [
            'name' => 'Organizations',
            'items' => [
                ['name' => 'All Organizations', 'description' => 'View organization directory', 'icon' => 'o-building-office', 'link' => '/organizations'],
                ['name' => 'Add Organization', 'description' => 'Create new organization', 'icon' => 'o-plus', 'link' => '/organizations/create'],
                ['name' => 'Org Analytics', 'description' => 'View organization insights', 'icon' => 'o-chart-pie', 'link' => '/organizations/analytics'],
            ]
        ],
        [
            'name' => 'Transactions',
            'items' => [
                ['name' => 'All Transactions', 'description' => 'View all transactions', 'icon' => 'o-arrows-right-left', 'link' => '/transactions'],
                ['name' => 'Pending Transactions', 'description' => 'Review pending transactions', 'icon' => 'o-clock', 'link' => '/transactions/pending'],
                ['name' => 'Transaction Analytics', 'description' => 'Analyze transaction data', 'icon' => 'o-chart-bar', 'link' => '/transactions/analytics'],
                ['name' => 'High Value Transactions', 'description' => 'Monitor high-value transactions', 'icon' => 'o-star', 'link' => '/transactions/high-value'],
                ['name' => 'Suspicious Transactions', 'description' => 'Review suspicious activity', 'icon' => 'o-eye-slash', 'link' => '/transactions/suspicious'],
            ]
        ],
        [
            'name' => 'KYC & Compliance',
            'items' => [
                ['name' => 'KYC Dashboard', 'description' => 'Monitor KYC status', 'icon' => 'o-shield-check', 'link' => '/kyc'],
                ['name' => 'Pending Reviews', 'description' => 'Review pending KYC', 'icon' => 'o-clock', 'link' => '/kyc/pending'],
                ['name' => 'Compliance Reports', 'description' => 'Generate compliance reports', 'icon' => 'o-document-check', 'link' => '/reports/compliance'],
            ]
        ],
        [
            'name' => 'Financial',
            'items' => [
                ['name' => 'Financial Dashboard', 'description' => 'View financial overview', 'icon' => 'o-banknotes', 'link' => '/financial'],
                ['name' => 'Account Management', 'description' => 'Manage accounts', 'icon' => 'o-credit-card', 'link' => '/accounts'],
                ['name' => 'Balance Analysis', 'description' => 'Analyze balances', 'icon' => 'o-scale', 'link' => '/balances/distribution'],
            ]
        ],
        [
            'name' => 'Risk Management',
            'items' => [
                ['name' => 'Risk Overview', 'description' => 'View risk dashboard', 'icon' => 'o-exclamation-triangle', 'link' => '/risk'],
                ['name' => 'Risk Alerts', 'description' => 'View active alerts', 'icon' => 'o-bell-alert', 'link' => '/risk/alerts'],
                ['name' => 'High Risk Entities', 'description' => 'Monitor high-risk entities', 'icon' => 'o-exclamation-circle', 'link' => '/risk/high-value-no-kyc'],
            ]
        ]
    ]"
/>

{{-- Theme Toggle --}}
<x-theme-toggle class="fixed z-50 bottom-4 right-4" />


</div>

{{-- Keyboard Shortcuts Help --}}



</body>
</html>
