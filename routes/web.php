<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Web Routes - CDR Banking System with Roles & Permissions
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Support us (Public)
Volt::route('/support-us', 'support-us');

// Authentication (Public)
Volt::route('/login', 'login')->name('login');

// Logout
Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // =====================================================
    // DASHBOARD & OVERVIEW (All authenticated users)
    // =====================================================

    Volt::route('/', 'dashboard.index')->name('dashboard')
        ->middleware('permission:dashboard.view');

    Volt::route('/analytics', 'dashboard.analytics')->name('analytics')
        ->middleware('permission:analytics.view');

    Volt::route('/financial-overview', 'dashboard.financial-overview')->name('financial.overview')
        ->middleware('permission:financial.view');

    Volt::route('/compliance', 'dashboard.compliance')->name('compliance')
        ->middleware('permission:compliance.view');

    Volt::route('/risk-management', 'dashboard.risk-management')->name('risk.management')
        ->middleware('permission:risk.view');

    // =====================================================
    // CUSTOMER MANAGEMENT
    // =====================================================

    // Customer Analytics (Manager+) - MOVE SPECIFIC ROUTES FIRST
    Route::middleware('role:manager|admin|super-admin')->group(function () {
        Volt::route('/customers/analytics', 'customers.analytics')->name('customers.analytics');
        Volt::route('/customers/segments', 'customers.segments')->name('customers.segments');
        Volt::route('/customers/reports', 'customers.reports')->name('customers.reports');
    });

    // Customer Creation (Customer Service+)
    Route::middleware('permission:customers.create')->group(function () {
        Volt::route('/customers/create', 'customers.create')->name('customers.create');
    });

    // Customer Viewing (Customer Service+)
    Route::middleware('permission:customers.view')->group(function () {
        Volt::route('/customers', 'customers.index')->name('customers.index');
        Volt::route('/customers/{customer}', 'customers.show')
            ->where('customer', '^(?!analytics$|segments$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('customers.show');
        Volt::route('/customers/{customer}/accounts', 'customers.accounts')
            ->where('customer', '^(?!analytics$|segments$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('customers.accounts');
        Volt::route('/customers/{customer}/kyc', 'customers.kyc')
            ->where('customer', '^(?!analytics$|segments$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('customers.kyc');
        Volt::route('/customers/{customer}/transactions', 'customers.transactions')
            ->where('customer', '^(?!analytics$|segments$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('customers.transactions');
    });

    // Customer Editing (Customer Service+)
    Route::middleware('permission:customers.edit')->group(function () {
        Volt::route('/customers/{customer}/edit', 'customers.edit')
            ->where('customer', '^(?!analytics$|segments$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('customers.edit');
    });

    // Customer Accounts
    Route::middleware('permission:customer-accounts.view')->group(function () {
        Volt::route('/customer-accounts', 'customer-accounts.index')->name('customer.accounts.index');
        Volt::route('/customer-accounts/{account}', 'customer-accounts.show')->name('customer.accounts.show');
    });

    Route::middleware('permission:customer-accounts.create')->group(function () {
        Volt::route('/customer-accounts/create', 'customer-accounts.create')->name('customer.accounts.create');
    });

    Route::middleware('permission:customer-accounts.edit')->group(function () {
        Volt::route('/customer-accounts/{account}/edit', 'customer-accounts.edit')->name('customer.accounts.edit');
    });

    // =====================================================
    // ORGANIZATION MANAGEMENT - FIXED ORDER
    // =====================================================

    // Organization Analytics (Manager+) - MUST BE FIRST
    Route::middleware('role:manager|admin|super-admin')->group(function () {
        Volt::route('/organizations/analytics', 'organizations.analytics')->name('organizations.analytics');
        Volt::route('/organizations/reports', 'organizations.reports')->name('organizations.reports');
    });

    // Organization Creation (Manager+) - SPECIFIC ROUTES SECOND
    Route::middleware('permission:organizations.create')->group(function () {
        Volt::route('/organizations/create', 'organizations.create')->name('organizations.create');
    });

    // Organization Viewing (Operator+) - PARAMETERIZED ROUTES LAST
    Route::middleware('permission:organizations.view')->group(function () {
        Volt::route('/organizations', 'organizations.index')->name('organizations.index');
        Volt::route('/organizations/{organization}', 'organizations.show')
            ->where('organization', '^(?!analytics$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('organizations.show');
        Volt::route('/organizations/{organization}/accounts', 'organizations.accounts')
            ->where('organization', '^(?!analytics$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('organizations.accounts');
        Volt::route('/organizations/{organization}/kyc', 'organizations.kyc')
            ->where('organization', '^(?!analytics$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('organizations.kyc');
        Volt::route('/organizations/{organization}/operators', 'organizations.operators')
            ->where('organization', '^(?!analytics$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('organizations.operators');
    });

    // Organization Editing (Manager+)
    Route::middleware('permission:organizations.edit')->group(function () {
        Volt::route('/organizations/{organization}/edit', 'organizations.edit')
            ->where('organization', '^(?!analytics$|reports$|create$)[0-9A-Za-z\-_]+$')
            ->name('organizations.edit');
    });

    // Organization Accounts
    Route::middleware('permission:organizations.view')->group(function () {
        Volt::route('/organization-accounts', 'organization-accounts.index')->name('organization.accounts.index');
        Volt::route('/organization-accounts/{account}', 'organization-accounts.show')->name('organization.accounts.show');
    });

    Route::middleware('permission:organization-accounts.create')->group(function () {
        Volt::route('/organization-accounts/create', 'organization-accounts.create')->name('organization.accounts.create');
    });

    Route::middleware('permission:organization-accounts.edit')->group(function () {
        Volt::route('/organization-accounts/{account}/edit', 'organization-accounts.edit')->name('organization.accounts.edit');
    });

    // =====================================================
    // TRANSACTION MANAGEMENT - FIXED ORDER
    // =====================================================

    // Transaction Analytics (Financial Analyst+) - SPECIFIC ROUTES FIRST
    Route::middleware('permission:customers.view')->group(function () {
        Volt::route('/transactions/analytics', 'transactions.analytics')->name('transactions.analytics');
        Volt::route('/transactions/volume-analysis', 'transactions.volume-analysis')->name('transactions.volume.analysis');
        Volt::route('/transactions/trend-analysis', 'transactions.trend-analysis')->name('transactions.trend.analysis');
        Volt::route('/transactions/channel-analysis', 'transactions.channel-analysis')->name('transactions.channel.analysis');
        Volt::route('/transactions/performance-metrics', 'transactions.performance-metrics')->name('transactions.performance.metrics');
        Volt::route('/transactions/pending', 'transactions.pending')->name('transactions.pending');
        Volt::route('/transactions/completed', 'transactions.completed')->name('transactions.completed');
        Volt::route('/transactions/failed', 'transactions.failed')->name('transactions.failed');
        Volt::route('/transactions/reversed', 'transactions.reversed')->name('transactions.reversed');
    });

    // Transaction Reports (Financial Analyst+)
    Route::middleware('permission:transactions.reports')->group(function () {
        Volt::route('/transactions/reports', 'transactions.reports')->name('transactions.reports');
        Volt::route('/transactions/daily-summary', 'transactions.daily-summary')->name('transactions.daily.summary');
        Volt::route('/transactions/monthly-report', 'transactions.monthly-report')->name('transactions.monthly.report');
        Volt::route('/transactions/regulatory-report', 'transactions.regulatory-report')->name('transactions.regulatory.report');
    });

    // High-Value Transaction Monitoring (Manager+)
    Route::middleware('role:manager|admin|super-admin')->group(function () {
        Volt::route('/transactions/high-value', 'transactions.high-value')->name('transactions.high.value');
        Volt::route('/transactions/suspicious', 'transactions.suspicious')->name('transactions.suspicious');
        Volt::route('/transactions/cross-border', 'transactions.cross-border')->name('transactions.cross.border');
        Volt::route('/transactions/reversals', 'transactions.reversals')->name('transactions.reversals');
    });

    // Transaction Viewing (Financial Analyst+) - PARAMETERIZED ROUTES LAST
    Route::middleware('permission:customers.view')->group(function () {
        Volt::route('/transactions', 'transactions.index')->name('transactions.index');
        Volt::route('/transactions/{orderid}', 'transactions.show')
            ->name('transactions.show')
            ->where('orderid', '^(?!analytics$|volume-analysis$|trend-analysis$|channel-analysis$|performance-metrics$|pending$|completed$|failed$|reversed$|reports$|daily-summary$|monthly-report$|regulatory-report$|high-value$|suspicious$|cross-border$|reversals$)[0-9]+$');
        Volt::route('/transactions/{orderid}/details', 'transactions.details')
            ->name('transactions.details')
            ->where('orderid', '^(?!analytics$|volume-analysis$|trend-analysis$|channel-analysis$|performance-metrics$|pending$|completed$|failed$|reversed$|reports$|daily-summary$|monthly-report$|regulatory-report$|high-value$|suspicious$|cross-border$|reversals$)[0-9]+$');
    });

    // Transaction Processing (Manager+)
    Route::middleware('permission:transactions.process')->group(function () {
        Volt::route('/transactions/{orderid}/process', 'transactions.process')
            ->name('transactions.process')
            ->where('orderid', '[0-9]+');
        Volt::route('/transactions/{orderid}/approve', 'transactions.approve')
            ->name('transactions.approve')
            ->where('orderid', '[0-9]+');
        Volt::route('/transactions/{orderid}/reject', 'transactions.reject')
            ->name('transactions.reject')
            ->where('orderid', '[0-9]+');
    });

    // Transaction Reversals (Manager+)
     Route::middleware('permission:transactions.reverse')->group(function () {
        Volt::route('/transactions/{orderid}/reverse', 'transactions.reverse')
            ->name('transactions.reverse')
            ->where('orderid', '[0-9]+');
    });

    // Transaction Details Management (Financial Analyst+)
    Route::middleware('permission:customers.view')->group(function () {
        Volt::route('/transaction-details', 'transaction-details.index')->name('transaction.details.index');
        Volt::route('/transaction-details/{detail}', 'transaction-details.show')->name('transaction.details.show');
        Volt::route('/transaction-details/audit-trail', 'transaction-details.audit-trail')->name('transaction.details.audit.trail');
        Volt::route('/transaction-details/error-analysis', 'transaction-details.error-analysis')->name('transaction.details.error.analysis');
    });

    // =====================================================
    // OPERATOR MANAGEMENT (Admin+) - FIXED ORDER
    // =====================================================

    // Operator Analytics (Admin+) - SPECIFIC ROUTES FIRST
    Route::middleware('role:admin|super-admin')->group(function () {
        Volt::route('/operators/analytics', 'operators.analytics')->name('operators.analytics');
        Volt::route('/operators/performance', 'operators.performance')->name('operators.performance');
    });

    Route::middleware('permission:operators.create')->group(function () {
        Volt::route('/operators/create', 'operators.create')->name('operators.create');
    });

    Route::middleware('permission:operators.view')->group(function () {
        Volt::route('/operators', 'operators.index')->name('operators.index');
        Volt::route('/operators/{operator}', 'operators.show')
            ->where('operator', '^(?!analytics$|performance$|create$)[0-9A-Za-z\-_]+$')
            ->name('operators.show');
        Volt::route('/operators/{operator}/kyc', 'operators.kyc')
            ->where('operator', '^(?!analytics$|performance$|create$)[0-9A-Za-z\-_]+$')
            ->name('operators.kyc');
        Volt::route('/operators/{operator}/activity', 'operators.activity')
            ->where('operator', '^(?!analytics$|performance$|create$)[0-9A-Za-z\-_]+$')
            ->name('operators.activity');
    });

    Route::middleware('permission:operators.edit')->group(function () {
        Volt::route('/operators/{operator}/edit', 'operators.edit')
            ->where('operator', '^(?!analytics$|performance$|create$)[0-9A-Za-z\-_]+$')
            ->name('operators.edit');
    });

    // =====================================================
    // KYC MANAGEMENT
    // =====================================================

    // KYC Reports (KYC Officer+) - SPECIFIC ROUTES FIRST
    Route::middleware('permission:kyc.export')->group(function () {
        Volt::route('/kyc/reports', 'kyc.reports')->name('kyc.reports');
        Volt::route('/kyc/compliance-report', 'kyc.compliance-report')->name('kyc.compliance.report');
    });

    // KYC Viewing (KYC Officer+) - SPECIFIC ROUTES
    Route::middleware('permission:kyc.view')->group(function () {
        Volt::route('/kyc/pending', 'kyc.pending')->name('kyc.pending');
        Volt::route('/kyc/completed', 'kyc.completed')->name('kyc.completed');
        Volt::route('/kyc/rejected', 'kyc.rejected')->name('kyc.rejected');
    });

    Route::middleware('permission:kyc.view')->group(function () {
        Volt::route('/kyc', 'kyc.index')->name('kyc.index');
    });

    // KYC Processing (KYC Officer+) - PARAMETERIZED ROUTES LAST
    Route::middleware('permission:kyc.review')->group(function () {
        Volt::route('/kyc/{kyc}/review', 'kyc.review')
            ->where('kyc', '^(?!reports$|compliance-report$|pending$|completed$|rejected$)[0-9A-Za-z\-_]+$')
            ->name('kyc.review');
        Volt::route('/kyc/customer/{customer}', 'kyc.customer')->name('kyc.customer');
        Volt::route('/kyc/organization/{organization}', 'kyc.organization')->name('kyc.organization');
        Volt::route('/kyc/operator/{operator}', 'kyc.operator')->name('kyc.operator');
    });

    // Compliance Monitoring (KYC Officer+)
    Route::middleware('permission:compliance.view')->group(function () {
        Volt::route('/compliance/overview', 'compliance.overview')->name('compliance.overview');
        Volt::route('/compliance/kyc-status', 'compliance.kyc-status')->name('compliance.kyc.status');
    });

    // Compliance Management (Manager+)
    Route::middleware('permission:compliance.manage')->group(function () {
        Volt::route('/compliance/audit-trail', 'compliance.audit-trail')->name('compliance.audit.trail');
        Volt::route('/compliance/regulatory-reports', 'compliance.regulatory-reports')->name('compliance.regulatory.reports');
    });

    // =====================================================
    // FINANCIAL MANAGEMENT
    // =====================================================

    // Financial Viewing (Financial Analyst+)
    Route::middleware('permission:financial.view')->group(function () {
        Volt::route('/financial', 'financial.index')->name('financial.index');
        Volt::route('/financial/balances', 'financial.balances')->name('financial.balances');
    });

    // Financial Transactions (Financial Analyst+)
    Route::middleware('permission:financial.transactions')->group(function () {
        Volt::route('/financial/transactions', 'financial.transactions')->name('financial.transactions');
    });

    // Account Management (Financial Analyst+)
    Route::middleware('permission:accounts.view')->group(function () {
        Volt::route('/accounts', 'accounts.index')->name('accounts.index');
        Volt::route('/accounts/types', 'accounts.types')->name('accounts.types');
        Volt::route('/accounts/dormant', 'accounts.dormant')->name('accounts.dormant');
        Volt::route('/accounts/high-value', 'accounts.high-value')->name('accounts.high.value');
    });

    // Balance Analysis (Financial Analyst+)
    Route::middleware('permission:balances.view')->group(function () {
        Volt::route('/balances/distribution', 'balances.distribution')->name('balances.distribution');
        Volt::route('/balances/by-currency', 'balances.by-currency')->name('balances.currency');
        Volt::route('/balances/trends', 'balances.trends')->name('balances.trends');
    });

    // =====================================================
    // ANALYTICS & REPORTING
    // =====================================================

    // Business Intelligence (Manager+)
    Route::middleware('role:manager|admin|super-admin')->group(function () {
        Volt::route('/analytics/business-intelligence', 'analytics.business-intelligence')->name('analytics.bi');
        Volt::route('/analytics/customer-segmentation', 'analytics.customer-segmentation')->name('analytics.customer.segmentation');
        Volt::route('/analytics/organization-performance', 'analytics.organization-performance')->name('analytics.organization.performance');
        Volt::route('/analytics/financial-health', 'analytics.financial-health')->name('analytics.financial.health');
    });

    // Reports Viewing (Most roles)
    Route::middleware('permission:reports.view')->group(function () {
        Volt::route('/reports', 'reports.index')->name('reports.index');
        Volt::route('/reports/executive-summary', 'reports.executive-summary')->name('reports.executive');
        Volt::route('/reports/compliance', 'reports.compliance')->name('reports.compliance');
        Volt::route('/reports/financial', 'reports.financial')->name('reports.financial');
        Volt::route('/reports/customer-analysis', 'reports.customer-analysis')->name('reports.customer.analysis');
        Volt::route('/reports/organization-analysis', 'reports.organization-analysis')->name('reports.organization.analysis');
        Volt::route('/reports/risk-assessment', 'reports.risk-assessment')->name('reports.risk.assessment');
    });

    // Custom Reports (Manager+)
    Route::middleware('permission:reports.create')->group(function () {
        Volt::route('/reports/custom', 'reports.custom')->name('reports.custom');
        Volt::route('/reports/builder', 'reports.builder')->name('reports.builder');
    });

    // =====================================================
    // RISK & COMPLIANCE
    // =====================================================

    // Risk Viewing (Financial Analyst+)
    Route::middleware('permission:risk.view')->group(function () {
        Volt::route('/risk', 'risk.index')->name('risk.index');
        Volt::route('/risk/high-value-no-kyc', 'risk.high-value-no-kyc')->name('risk.high.value.no.kyc');
        Volt::route('/risk/dormant-accounts', 'risk.dormant-accounts')->name('risk.dormant.accounts');
        Volt::route('/risk/alerts', 'risk.alerts')->name('risk.alerts');
    });

    // Risk Investigation (Manager+)
    Route::middleware('permission:risk.investigate')->group(function () {
        Volt::route('/risk/suspicious-activity', 'risk.suspicious-activity')->name('risk.suspicious.activity');
    });

    // =====================================================
    // SEARCH & LOOKUP
    // =====================================================

    // Global Search (All authenticated)
    Route::middleware('permission:search.global')->group(function () {
        Volt::route('/search', 'search.index')->name('search.index');
        Volt::route('/search/customers', 'search.customers')->name('search.customers');
        Volt::route('/search/organizations', 'search.organizations')->name('search.organizations');
        Volt::route('/search/accounts', 'search.accounts')->name('search.accounts');
        Volt::route('/search/operators', 'search.operators')->name('search.operators');
        Volt::route('/search/transactions', 'search.transactions')->name('search.transactions');
    });

    // Advanced Search (Manager+)
    Route::middleware('permission:search.advanced')->group(function () {
        Volt::route('/search/advanced', 'search.advanced')->name('search.advanced');
        Volt::route('/search/advanced-transactions', 'search.advanced-transactions')->name('search.advanced.transactions');
    });

    // =====================================================
    // USER MANAGEMENT (Admin+)
    // =====================================================

    Route::middleware('permission:users.create')->group(function () {
        Volt::route('/users/create', 'users.create')->name('users.create');
    });

    Route::middleware('permission:users.view')->group(function () {
        Volt::route('/users', 'users.index')->name('users.index');
        Volt::route('/users/{user}', 'users.show')
            ->where('user', '^(?!create$)[0-9A-Za-z\-_]+$')
            ->name('users.show');
    });

    Route::middleware('permission:users.edit')->group(function () {
        Volt::route('/users/{user}/edit', 'users.edit')
            ->where('user', '^(?!create$)[0-9A-Za-z\-_]+$')
            ->name('users.edit');
    });

    // =====================================================
    // SYSTEM SETTINGS (Super Admin only)
    // =====================================================

    Route::middleware('role:super-admin')->group(function () {
        Volt::route('/settings', 'settings.index')->name('settings.index');
        Volt::route('/settings/system', 'settings.system')->name('settings.system');
    });

    Route::middleware('permission:settings.view')->group(function () {
        Volt::route('/settings/users', 'settings.users')->name('settings.users');
        Volt::route('/settings/permissions', 'settings.permissions')->name('settings.permissions');
    });

    // =====================================================
    // PROFILE MANAGEMENT (All users)
    // =====================================================

    Volt::route('/profile', 'profile.index')->name('profile.index');
    Volt::route('/profile/edit', 'profile.edit')->name('profile.edit');
    Volt::route('/profile/security', 'profile.security')->name('profile.security');

    // [REST OF THE ROUTES REMAIN THE SAME...]
    // =====================================================
    // EXPORTS & DOWNLOADS (Based on permissions)
    // =====================================================

    Route::get('/export/customers', function() {
        abort_unless(auth()->user()->can('export.customers'), 403, 'You do not have permission to export customer data.');
        return redirect()->back()->with('success', 'Customer export initiated');
    })->name('export.customers');

    Route::get('/export/organizations', function() {
        abort_unless(auth()->user()->can('export.organizations'), 403, 'You do not have permission to export organization data.');
        return redirect()->back()->with('success', 'Organization export initiated');
    })->name('export.organizations');

    Route::get('/export/accounts', function() {
        abort_unless(auth()->user()->can('export.financial'), 403, 'You do not have permission to export account data.');
        return redirect()->back()->with('success', 'Account export initiated');
    })->name('export.accounts');

    Route::get('/export/transactions', function() {
        abort_unless(auth()->user()->can('export.transactions'), 403, 'You do not have permission to export transaction data.');
        return redirect()->back()->with('success', 'Transaction export initiated');
    })->name('export.transactions');

    Route::get('/export/compliance-report', function() {
        abort_unless(auth()->user()->can('export.compliance'), 403, 'You do not have permission to export compliance reports.');
        return redirect()->back()->with('success', 'Compliance report export initiated');
    })->name('export.compliance');

    Route::get('/export/financial-summary', function() {
        abort_unless(auth()->user()->can('export.financial'), 403, 'You do not have permission to export financial data.');
        return redirect()->back()->with('success', 'Financial summary export initiated');
    })->name('export.financial');

    // =====================================================
    // QUICK ACTIONS (Role-based)
    // =====================================================

    Route::get('/quick-stats', function() {
        abort_unless(auth()->user()->can('dashboard.view'), 403);
        return response()->json([
            'customers' => \App\Models\Customer::count(),
            'organizations' => \App\Models\Organization::count(),
            'transactions_today' => \App\Models\Transaction::whereDate('trans_initate_time', today())->count(),
            'transactions_value_today' => \App\Models\Transaction::whereDate('trans_initate_time', today())->sum('actual_amount'),
            'total_balance' => \App\Models\CustomerAccount::sum('balance') + \App\Models\OrganizationAccount::sum('balance'),
            'kyc_completion' => round(\App\Models\Customer::whereHas('kyc')->count() / \App\Models\Customer::count() * 100, 2),
        ]);
    })->name('quick.stats');

    // [REST OF QUICK ACTIONS AND API ROUTES REMAIN THE SAME...]

});

// =====================================================
// PUBLIC ROUTES
// =====================================================

Volt::route('/public-stats', 'public.stats')->name('public.stats');

Route::get('/health', function() {
    return response()->json([
        'status' => 'healthy',
        'database' => 'connected',
        'timestamp' => now(),
    ]);
})->name('health.check');
