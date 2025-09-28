<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any clients.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'clients.view') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view the client.
     */
    public function view(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'clients.view') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can create clients.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'clients.create') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the client.
     */
    public function update(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'clients.update') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the client.
     */
    public function delete(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'clients.delete') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can restore the client.
     */
    public function restore(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'clients.restore') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the client.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'clients.force_delete') || 
               $this->isSuperAdmin($user);
    }
}