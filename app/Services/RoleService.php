<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RoleService
{
    /**
     * Get all roles with pagination
     */
    public function getAllRoles(array $filters = [], int $perPage = 15)
    {
        try {
            $query = Role::query();

            // Filter by name
            if (!empty($filters['name'])) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }

            // Filter by guard name
            if (!empty($filters['guard_name'])) {
                $query->where('guard_name', $filters['guard_name']);
            }

            return $query->with('permissions')->paginate($perPage);
        } catch (Exception $e) {
            Log::error('Error getting all roles: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get role by ID
     */
    public function getRoleById(int $id): ?Role
    {
        try {
            return Role::with('permissions')->find($id);
        } catch (Exception $e) {
            Log::error('Error getting role by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create new role
     */
    public function createRole(array $data): Role
    {
        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'web',
                'description' => $data['description'] ?? null
            ]);

            // Attach permissions if provided
            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            DB::commit();
            return $role->load('permissions');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update existing role
     */
    public function updateRole(int $id, array $data): ?Role
    {
        DB::beginTransaction();
        try {
            $role = Role::find($id);
            if (!$role) {
                return null;
            }

            $role->update([
                'name' => $data['name'] ?? $role->name,
                'description' => $data['description'] ?? $role->description
            ]);

            // Update permissions if provided
            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            DB::commit();
            return $role->load('permissions');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete role
     */
    public function deleteRole(int $id): bool
    {
        DB::beginTransaction();
        try {
            $role = Role::find($id);
            if (!$role) {
                return false;
            }

            // Check if role is assigned to users
            if ($role->users()->exists()) {
                throw new Exception('Cannot delete role that is assigned to users');
            }

            $role->delete();
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions(): array
    {
        try {
            return Permission::all()->groupBy('group')->toArray();
        } catch (Exception $e) {
            Log::error('Error getting all permissions: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(int $userId, int $roleId): bool
    {
        DB::beginTransaction();
        try {
            $user = User::find($userId);
            $role = Role::find($roleId);

            if (!$user || !$role) {
                return false;
            }

            $user->assignRole($role);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error assigning role to user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        DB::beginTransaction();
        try {
            $user = User::find($userId);
            $role = Role::find($roleId);

            if (!$user || !$role) {
                return false;
            }

            $user->removeRole($role);
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error removing role from user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(int $roleId, int $perPage = 15)
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                return null;
            }

            return $role->users()->paginate($perPage);
        } catch (Exception $e) {
            Log::error('Error getting users by role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync permissions for role
     */
    public function syncPermissions(int $roleId, array $permissionIds): bool
    {
        DB::beginTransaction();
        try {
            $role = Role::find($roleId);
            if (!$role) {
                return false;
            }

            $permissions = Permission::whereIn('id', $permissionIds)->get();
            $role->syncPermissions($permissions);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error syncing permissions: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get role statistics
     */
    public function getRoleStatistics(): array
    {
        try {
            $totalRoles = Role::count();
            $rolesWithUsers = Role::has('users')->count();
            $unusedRoles = Role::doesntHave('users')->count();

            return [
                'total_roles' => $totalRoles,
                'roles_with_users' => $rolesWithUsers,
                'unused_roles' => $unusedRoles
            ];
        } catch (Exception $e) {
            Log::error('Error getting role statistics: ' . $e->getMessage());
            throw $e;
        }
    }
}