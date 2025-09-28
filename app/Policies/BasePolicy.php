<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user has a specific permission.
     *
     * @param  \App\Models\User  $user
     * @param  string  $permission
     * @return bool
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        return $user->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('name', $permission);
        })->exists();
    }

    /**
     * Determine if the user has any of the given permissions.
     *
     * @param  \App\Models\User  $user
     * @param  array  $permissions
     * @return bool
     */
    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given permissions.
     *
     * @param  \App\Models\User  $user
     * @param  array  $permissions
     * @return bool
     */
    protected function hasAllPermissions(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user has a specific role.
     *
     * @param  \App\Models\User  $user
     * @param  string  $role
     * @return bool
     */
    protected function hasRole(User $user, string $role): bool
    {
        return $user->roles()->where('name', $role)->exists();
    }

    /**
     * Determine if the user has any of the given roles.
     *
     * @param  \App\Models\User  $user
     * @param  array  $roles
     * @return bool
     */
    protected function hasAnyRole(User $user, array $roles): bool
    {
        return $user->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Determine if the user is an administrator.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function isAdmin(User $user): bool
    {
        return $this->hasRole($user, 'admin') || $this->hasRole($user, 'super_admin');
    }

    /**
     * Determine if the user is a super administrator.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function isSuperAdmin(User $user): bool
    {
        return $this->hasRole($user, 'super_admin');
    }
}