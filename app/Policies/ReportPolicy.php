<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view financial reports.
     */
    public function viewFinancial(User $user): bool
    {
        return $this->hasPermission($user, 'reports.financial') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can generate financial PDF reports.
     */
    public function generateFinancialPdf(User $user): bool
    {
        return $this->hasPermission($user, 'reports.pdf') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view dashboard metrics.
     */
    public function viewDashboard(User $user): bool
    {
        return $this->hasPermission($user, 'reports.dashboard') || 
               $this->isAdmin($user) ||
               $this->hasAnyPermission($user, [
                   'clients.view',
                   'projects.view',
                   'invoices.view',
                   'payments.view'
               ]);
    }

    /**
     * Determine whether the user can view payment reports.
     */
    public function viewPayments(User $user): bool
    {
        return $this->hasPermission($user, 'reports.payments') || 
               $this->isAdmin($user) ||
               $this->hasPermission($user, 'payments.view');
    }

    /**
     * Determine whether the user can view project reports.
     */
    public function viewProjects(User $user): bool
    {
        return $this->hasPermission($user, 'reports.projects') || 
               $this->isAdmin($user) ||
               $this->hasPermission($user, 'projects.view');
    }

    /**
     * Determine whether the user can view client reports.
     */
    public function viewClients(User $user): bool
    {
        return $this->hasPermission($user, 'reports.clients') || 
               $this->isAdmin($user) ||
               $this->hasPermission($user, 'clients.view');
    }
}