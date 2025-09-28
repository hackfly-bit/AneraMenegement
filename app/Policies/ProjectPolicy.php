<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'projects.view') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->hasPermission($user, 'projects.view') || 
               $this->isAdmin($user) ||
               $this->isProjectMember($user, $project);
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'projects.create') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->hasPermission($user, 'projects.update') || 
               $this->isAdmin($user) ||
               $this->isProjectManager($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->hasPermission($user, 'projects.delete') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can restore the project.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->hasPermission($user, 'projects.restore') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the project.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->hasPermission($user, 'projects.force_delete') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Check if user is a project member.
     */
    protected function isProjectMember(User $user, Project $project): bool
    {
        return $project->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user is the project manager.
     */
    protected function isProjectManager(User $user, Project $project): bool
    {
        return $project->manager_id === $user->id;
    }
}