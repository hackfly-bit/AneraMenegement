<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

class AuthorizationService
{
    /**
     * Cache key for user permissions.
     */
    protected const PERMISSIONS_CACHE_KEY = 'user_permissions_';
    
    /**
     * Cache key for user roles.
     */
    protected const ROLES_CACHE_KEY = 'user_roles_';
    
    /**
     * Cache duration in seconds (1 hour).
     */
    protected const CACHE_DURATION = 3600;

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        $permissions = $this->getUserPermissions($user);
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the specified permissions.
     */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($user);
        return !empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Check if user has all of the specified permissions.
     */
    public function hasAllPermissions(User $user, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($user);
        return empty(array_diff($permissions, $userPermissions));
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(User $user, string $role): bool
    {
        $roles = $this->getUserRoles($user);
        return in_array($role, $roles);
    }

    /**
     * Check if user has any of the specified roles.
     */
    public function hasAnyRole(User $user, array $roles): bool
    {
        $userRoles = $this->getUserRoles($user);
        return !empty(array_intersect($roles, $userRoles));
    }

    /**
     * Check if user has all of the specified roles.
     */
    public function hasAllRoles(User $user, array $roles): bool
    {
        $userRoles = $this->getUserRoles($user);
        return empty(array_diff($roles, $userRoles));
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(User $user): bool
    {
        return $this->hasRole($user, 'admin') || $this->hasRole($user, 'super_admin');
    }

    /**
     * Check if user is a super administrator.
     */
    public function isSuperAdmin(User $user): bool
    {
        return $this->hasRole($user, 'super_admin');
    }

    /**
     * Get all permissions for a user.
     */
    public function getUserPermissions(User $user): array
    {
        return Cache::remember(
            self::PERMISSIONS_CACHE_KEY . $user->id,
            self::CACHE_DURATION,
            function () use ($user) {
                return $user->getAllPermissions()->pluck('name')->toArray();
            }
        );
    }

    /**
     * Get all roles for a user.
     */
    public function getUserRoles(User $user): array
    {
        return Cache::remember(
            self::ROLES_CACHE_KEY . $user->id,
            self::CACHE_DURATION,
            function () use ($user) {
                return $user->roles->pluck('name')->toArray();
            }
        );
    }

    /**
     * Clear user cache.
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget(self::PERMISSIONS_CACHE_KEY . $user->id);
        Cache::forget(self::ROLES_CACHE_KEY . $user->id);
    }

    /**
     * Clear all authorization cache.
     */
    public function clearCache(): void
    {
        Cache::flush();
    }

    /**
     * Assign role to user.
     */
    public function assignRole(User $user, string $role): void
    {
        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->syncWithoutDetaching([$roleModel->id]);
            $this->clearUserCache($user);
        }
    }

    /**
     * Remove role from user.
     */
    public function removeRole(User $user, string $role): void
    {
        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->detach($roleModel->id);
            $this->clearUserCache($user);
        }
    }

    /**
     * Give permission to user.
     */
    public function givePermission(User $user, string $permission): void
    {
        $permissionModel = Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $user->permissions()->syncWithoutDetaching([$permissionModel->id]);
            $this->clearUserCache($user);
        }
    }

    /**
     * Revoke permission from user.
     */
    public function revokePermission(User $user, string $permission): void
    {
        $permissionModel = Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $user->permissions()->detach($permissionModel->id);
            $this->clearUserCache($user);
        }
    }

    /**
     * Get all available permissions.
     */
    public function getAllPermissions(): array
    {
        return Permission::all()->pluck('name')->toArray();
    }

    /**
     * Get all available roles.
     */
    public function getAllRoles(): array
    {
        return Role::all()->pluck('name')->toArray();
    }
}