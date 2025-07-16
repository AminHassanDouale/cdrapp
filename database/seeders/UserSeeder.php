<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Define users for the CDR Banking system
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@cdrapp.com',
                'password' => 'password',
                'role' => 'super-admin',
                'avatar' => null,
            ],
            [
                'name' => 'System Administrator',
                'email' => 'admin@cdrapp.com',
                'password' => 'password',
                'role' => 'admin',
                'avatar' => null,
            ],
            [
                'name' => 'Banking Manager',
                'email' => 'manager@cdrapp.com',
                'password' => 'password',
                'role' => 'manager',
                'avatar' => null,
            ],
            [
                'name' => 'KYC Officer',
                'email' => 'kyc@cdrapp.com',
                'password' => 'password',
                'role' => 'kyc-officer',
                'avatar' => null,
            ],
            [
                'name' => 'Financial Analyst',
                'email' => 'finance@cdrapp.com',
                'password' => 'password',
                'role' => 'financial-analyst',
                'avatar' => null,
            ],
            [
                'name' => 'Customer Service Rep',
                'email' => 'support@cdrapp.com',
                'password' => 'password',
                'role' => 'customer-service',
                'avatar' => null,
            ],
            [
                'name' => 'System Operator',
                'email' => 'operator@cdrapp.com',
                'password' => 'password',
                'role' => 'operator',
                'avatar' => null,
            ],
            [
                'name' => 'Compliance Auditor',
                'email' => 'auditor@cdrapp.com',
                'password' => 'password',
                'role' => 'auditor',
                'avatar' => null,
            ],

            // Additional sample users for testing
            [
                'name' => 'Ahmed Hassan Mohamed',
                'email' => 'ahmed@cdrapp.com',
                'password' => 'password',
                'role' => 'manager',
                'avatar' => null,
            ],
            [
                'name' => 'Fatima Ali Abdourahim',
                'email' => 'fatima@cdrapp.com',
                'password' => 'password',
                'role' => 'kyc-officer',
                'avatar' => null,
            ],
            [
                'name' => 'Omar Ismael Youssouf',
                'email' => 'omar@cdrapp.com',
                'password' => 'password',
                'role' => 'financial-analyst',
                'avatar' => null,
            ],
            [
                'name' => 'Amina Mohamed Hassan',
                'email' => 'amina@cdrapp.com',
                'password' => 'password',
                'role' => 'customer-service',
                'avatar' => null,
            ],
            [
                'name' => 'Said Ali Djama',
                'email' => 'said@cdrapp.com',
                'password' => 'password',
                'role' => 'operator',
                'avatar' => null,
            ],
            [
                'name' => 'Khadija Ibrahim Nour',
                'email' => 'khadija@cdrapp.com',
                'password' => 'password',
                'role' => 'customer-service',
                'avatar' => null,
            ],
            [
                'name' => 'Abdourahman Farah Ali',
                'email' => 'abdourahman@cdrapp.com',
                'password' => 'password',
                'role' => 'financial-analyst',
                'avatar' => null,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'avatar' => $userData['avatar'],
                'email_verified_at' => now(),
            ]);

            // Assign role to user
            $user->assignRole($userData['role']);

            $this->command->info("User '{$userData['name']}' created with role '{$userData['role']}'");
        }

        $this->command->info('Users created successfully!');
    }
}
