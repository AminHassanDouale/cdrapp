<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions organized by modules
        $permissions = [
            // Dashboard & Analytics
            'dashboard' => [
                'dashboard.view',
                'analytics.view',
                'analytics.export',
                'reports.view',
                'reports.create',
                'reports.export',
            ],

            // Customer Management
            'customers' => [
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',
                'customers.export',
                'customer-accounts.view',
                'customer-accounts.create',
                'customer-accounts.edit',
                'customer-accounts.delete',
            ],

            // Organization Management
            'organizations' => [
                'organizations.view',
                'organizations.create',
                'organizations.edit',
                'organizations.delete',
                'organizations.export',
                'organization-accounts.view',
                'organization-accounts.create',
                'organization-accounts.edit',
                'organization-accounts.delete',
            ],

            // Operator Management
            'operators' => [
                'operators.view',
                'operators.create',
                'operators.edit',
                'operators.delete',
                'operators.export',
                'operators.manage-permissions',
            ],

            // KYC & Compliance
            'kyc' => [
                'kyc.view',
                'kyc.review',
                'kyc.approve',
                'kyc.reject',
                'kyc.export',
                'compliance.view',
                'compliance.manage',
                'compliance.audit',
            ],

            // Financial Management
            'financial' => [
                'financial.view',
                'financial.transactions',
                'accounts.view',
                'accounts.create',
                'accounts.edit',
                'accounts.delete',
                'balances.view',
                'balances.manage',
            ],

            // Risk Management
            'risk' => [
                'risk.view',
                'risk.manage',
                'risk.alerts',
                'risk.investigate',
                'risk.resolve',
            ],

            // User & System Management
            'users' => [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'users.manage-roles',
                'users.manage-permissions',
            ],

            // System Administration
            'system' => [
                'settings.view',
                'settings.edit',
                'system.backup',
                'system.restore',
                'system.maintenance',
                'audit-logs.view',
            ],

            // Search & Export
            'search' => [
                'search.global',
                'search.advanced',
                'export.customers',
                'export.organizations',
                'export.financial',
                'export.compliance',
            ],
        ];

        // Create all permissions
        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Permission::create([
                    'name' => $permission,
                    'guard_name' => 'web'
                ]);
            }
        }

        $this->command->info('Permissions created successfully!');
    }
}
