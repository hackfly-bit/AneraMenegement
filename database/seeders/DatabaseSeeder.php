<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run role and permission seeder first
        $this->call(RolePermissionSeeder::class);
        
        // Create default users with roles
        $this->createDefaultUsers();
    }
    
    /**
     * Create default users with roles.
     */
    protected function createDefaultUsers(): void
    {
        // Create super admin user
        $superAdmin = \App\Models\User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $superAdmin->assignRole('super_admin');
        
        // Create admin user
        $admin = \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        
        // Create manager user
        $manager = \App\Models\User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $manager->assignRole('manager');
        
        // Create accountant user
        $accountant = \App\Models\User::factory()->create([
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $accountant->assignRole('accountant');
        
        // Create employee user
        $employee = \App\Models\User::factory()->create([
            'name' => 'Employee User',
            'email' => 'employee@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $employee->assignRole('employee');
        
        // Create client user
        $client = \App\Models\User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $client->assignRole('client');
    }
}
