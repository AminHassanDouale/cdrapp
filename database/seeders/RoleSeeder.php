<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Define roles with their permissions
        $roles = [
            'super-admin' => [
                'description' => 'Full system access with all permissions',
                'permissions' => 'all', // Will get all permissions
            ],

            'admin' => [
                'description' => 'Administrative access to most features',
                'permissions' => [
                    // Dashboard & Analytics
                    'dashboard.view', 'analytics.view', 'analytics.export',
                    'reports.view', 'reports.create', 'reports.export',

                    // Customer Management
                    'customers.view', 'customers.create', 'customers.edit',
                    'customers.export', 'customer-accounts.view', 'customer-accounts.create',
                    'customer-accounts.edit',

                    // Organization Management
                    'organizations.view', 'organizations.create', 'organizations.edit',
                    'organizations.export', 'organization-accounts.view',
                    'organization-accounts.create', 'organization-accounts.edit',

                    // Operator Management
                    'operators.view', 'operators.create', 'operators.edit',
                    'operators.export',

                    // KYC & Compliance
                    'kyc.view', 'kyc.review', 'kyc.approve', 'kyc.reject',
                    'kyc.export', 'compliance.view', 'compliance.manage',

                    // Financial Management
                    'financial.view', 'financial.transactions', 'accounts.view',
                    'accounts.create', 'accounts.edit', 'balances.view',

                    // Risk Management
                    'risk.view', 'risk.manage', 'risk.alerts', 'risk.investigate',

                    // Search & Export
                    'search.global', 'search.advanced', 'export.customers',
                    'export.organizations', 'export.financial', 'export.compliance',
                ],
            ],

            'manager' => [
                'description' => 'Management level access to operations',
                'permissions' => [
                    // Dashboard & Analytics
                    'dashboard.view', 'analytics.view', 'reports.view',

                    // Customer Management
                    'customers.view', 'customers.create', 'customers.edit',
                    'customer-accounts.view', 'customer-accounts.create', 'customer-accounts.edit',

                    // Organization Management
                    'organizations.view', 'organizations.create', 'organizations.edit',
                    'organization-accounts.view', 'organization-accounts.create',

                    // KYC & Compliance
                    'kyc.view', 'kyc.review', 'kyc.approve', 'compliance.view',

                    // Financial Management
                    'financial.view', 'financial.transactions', 'accounts.view',
                    'balances.view',

                    // Risk Management
                    'risk.view', 'risk.alerts', 'risk.investigate',

                    // Search & Export
                    'search.global', 'export.customers', 'export.organizations',
                ],
            ],

            'kyc-officer' => [
                'description' => 'KYC and compliance specialist',
                'permissions' => [
                    // Dashboard & Analytics
                    'dashboard.view', 'analytics.view',

                    // Customer Management (limited)
                    'customers.view', 'customer-accounts.view',

                    // Organization Management (limited)
                    'organizations.view', 'organization-accounts.view',

                    // KYC & Compliance (full access)
                    'kyc.view', 'kyc.review', 'kyc.approve', 'kyc.reject',
                    'kyc.export', 'compliance.view', 'compliance.manage', 'compliance.audit',

                    // Risk Management
                    'risk.view', 'risk.alerts', 'risk.investigate',

                    // Search
                    'search.global', 'export.compliance',
                ],
            ],

            'financial-analyst' => [
                'description' => 'Financial operations and analysis',
                'permissions' => [
                    // Dashboard & Analytics
                    'dashboard.view', 'analytics.view', 'reports.view',

                    // Customer Management (view only)
                    'customers.view', 'customer-accounts.view',

                    // Organization Management (view only)
                    'organizations.view', 'organization-accounts.view',

                    // Financial Management (full access)
                    'financial.view', 'financial.transactions', 'accounts.view',
                    'accounts.create', 'accounts.edit', 'balances.view', 'balances.manage',

                    // Risk Management
                    'risk.view', 'risk.alerts',

                    // Search & Export
                    'search.global', 'export.financial',
                ],
            ],

            'customer-service' => [
                'description' => 'Customer service representative',
                'permissions' => [
                    // Dashboard & Analytics (limited)
                    'dashboard.view',

                    // Customer Management
                    'customers.view', 'customers.create', 'customers.edit',
                    'customer-accounts.view',

                    // Organization Management (limited)
                    'organizations.view', 'organization-accounts.view',

                    // KYC (limited)
                    'kyc.view',

                    // Search
                    'search.global',
                ],
            ],

            'operator' => [
                'description' => 'Basic operational access',
                'permissions' => [
                    // Dashboard & Analytics (limited)
                    'dashboard.view',

                    // Customer Management (view only)
                    'customers.view', 'customer-accounts.view',

                    // Organization Management (view only)
                    'organizations.view',

                    // Search
                    'search.global',
                ],
            ],

            'auditor' => [
                'description' => 'Audit and compliance monitoring',
                'permissions' => [
                    // Dashboard & Analytics
                    'dashboard.view', 'analytics.view', 'reports.view',

                    // View-only access to most modules
                    'customers.view', 'customer-accounts.view',
                    'organizations.view', 'organization-accounts.view',
                    'operators.view',

                    // Full compliance and audit access
                    'kyc.view', 'compliance.view', 'compliance.audit',
                    'risk.view', 'audit-logs.view',

                    // Search & Export
                    'search.global', 'search.advanced', 'export.compliance',
                ],
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $roleData) {
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web'
            ]);

            if ($roleData['permissions'] === 'all') {
                // Give all permissions to super-admin
                $role->givePermissionTo(Permission::all());
            } else {
                // Give specific permissions
                $role->givePermissionTo($roleData['permissions']);
            }

            $this->command->info("Role '{$roleName}' created with permissions");
        }

        $this->command->info('Roles created successfully!');
    }
}
