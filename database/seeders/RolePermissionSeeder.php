<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Client permissions
            ['name' => 'clients.view', 'display_name' => 'View Clients', 'module' => 'clients'],
            ['name' => 'clients.create', 'display_name' => 'Create Clients', 'module' => 'clients'],
            ['name' => 'clients.update', 'display_name' => 'Update Clients', 'module' => 'clients'],
            ['name' => 'clients.delete', 'display_name' => 'Delete Clients', 'module' => 'clients'],
            ['name' => 'clients.restore', 'display_name' => 'Restore Clients', 'module' => 'clients'],
            ['name' => 'clients.force_delete', 'display_name' => 'Force Delete Clients', 'module' => 'clients'],
            
            // Project permissions
            ['name' => 'projects.view', 'display_name' => 'View Projects', 'module' => 'projects'],
            ['name' => 'projects.create', 'display_name' => 'Create Projects', 'module' => 'projects'],
            ['name' => 'projects.update', 'display_name' => 'Update Projects', 'module' => 'projects'],
            ['name' => 'projects.delete', 'display_name' => 'Delete Projects', 'module' => 'projects'],
            ['name' => 'projects.restore', 'display_name' => 'Restore Projects', 'module' => 'projects'],
            ['name' => 'projects.force_delete', 'display_name' => 'Force Delete Projects', 'module' => 'projects'],
            
            // Invoice permissions
            ['name' => 'invoices.view', 'display_name' => 'View Invoices', 'module' => 'invoices'],
            ['name' => 'invoices.create', 'display_name' => 'Create Invoices', 'module' => 'invoices'],
            ['name' => 'invoices.update', 'display_name' => 'Update Invoices', 'module' => 'invoices'],
            ['name' => 'invoices.delete', 'display_name' => 'Delete Invoices', 'module' => 'invoices'],
            ['name' => 'invoices.pdf', 'display_name' => 'Generate Invoice PDF', 'module' => 'invoices'],
            ['name' => 'invoices.send', 'display_name' => 'Send Invoices', 'module' => 'invoices'],
            ['name' => 'invoices.terms', 'display_name' => 'Manage Invoice Terms', 'module' => 'invoices'],
            
            // Payment permissions
            ['name' => 'payments.view', 'display_name' => 'View Payments', 'module' => 'payments'],
            ['name' => 'payments.create', 'display_name' => 'Create Payments', 'module' => 'payments'],
            ['name' => 'payments.update', 'display_name' => 'Update Payments', 'module' => 'payments'],
            ['name' => 'payments.delete', 'display_name' => 'Delete Payments', 'module' => 'payments'],
            
            // Report permissions
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'module' => 'reports'],
            ['name' => 'reports.financial', 'display_name' => 'View Financial Reports', 'module' => 'reports'],
            ['name' => 'reports.pdf', 'display_name' => 'Generate Report PDF', 'module' => 'reports'],
            ['name' => 'reports.dashboard', 'display_name' => 'View Dashboard', 'module' => 'reports'],
            ['name' => 'reports.payments', 'display_name' => 'View Payment Reports', 'module' => 'reports'],
            ['name' => 'reports.projects', 'display_name' => 'View Project Reports', 'module' => 'reports'],
            ['name' => 'reports.clients', 'display_name' => 'View Client Reports', 'module' => 'reports'],
            
            // Admin permissions
            ['name' => 'admin', 'display_name' => 'Admin Access', 'module' => 'admin'],
            ['name' => 'users.view', 'display_name' => 'View Users', 'module' => 'admin'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'admin'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'module' => 'admin'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'module' => 'admin'],
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'module' => 'admin'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'module' => 'admin'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'module' => 'admin'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'module' => 'admin'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                array_merge($permission, [
                    'description' => $permission['display_name'] . ' permission',
                    'is_active' => true,
                ])
            );
        }

        // Create roles
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full access to all system features'
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access to most features'
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Management access to projects and team'
            ],
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Access to financial and invoicing features'
            ],
            [
                'name' => 'employee',
                'display_name' => 'Employee',
                'description' => 'Basic access to assigned projects'
            ],
            [
                'name' => 'client',
                'display_name' => 'Client',
                'description' => 'Limited access to own projects and invoices'
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to roles.
     */
    protected function assignPermissionsToRoles(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $allPermissions = Permission::all();
            $superAdmin->permissions()->sync($allPermissions->pluck('id'));
        }

        // Admin - Most permissions except super admin specific
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $adminPermissions = Permission::whereNotIn('name', [
                'users.delete',
                'roles.delete',
                'clients.force_delete',
                'projects.force_delete',
                'invoices.force_delete',
            ])->get();
            $admin->permissions()->sync($adminPermissions->pluck('id'));
        }

        // Manager - Project and client management
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $managerPermissions = Permission::whereIn('module', [
                'clients',
                'projects',
                'reports'
            ])->whereNotIn('name', [
                'clients.delete',
                'clients.force_delete',
                'projects.delete',
                'projects.force_delete',
            ])->get();
            $manager->permissions()->sync($managerPermissions->pluck('id'));
        }

        // Accountant role - can manage invoices and payments
        $accountant = Role::where('name', 'accountant')->first();
        if ($accountant) {
            $accountantPermissions = Permission::whereIn('module', ['invoices', 'payments', 'reports'])
                ->whereNotIn('name', ['invoices.delete', 'payments.delete'])
                ->get();
            $accountant->permissions()->sync($accountantPermissions->pluck('id'));
        }

        // Employee - Basic project access
        $employee = Role::where('name', 'employee')->first();
        if ($employee) {
            $employeePermissions = Permission::whereIn('name', [
                'projects.view',
                'clients.view',
                'reports.dashboard',
            ])->get();
            $employee->permissions()->sync($employeePermissions->pluck('id'));
        }

        // Client - Limited access
        $client = Role::where('name', 'client')->first();
        if ($client) {
            $clientPermissions = Permission::whereIn('name', [
                'projects.view',
                'invoices.view',
                'payments.view',
            ])->get();
            $client->permissions()->sync($clientPermissions->pluck('id'));
        }
    }
}