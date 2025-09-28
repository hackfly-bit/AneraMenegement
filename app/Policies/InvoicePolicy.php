<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'invoices.view') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.view') || 
               $this->isAdmin($user) ||
               $this->isInvoiceCreator($user, $invoice);
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'invoices.create') || 
               $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.update') || 
               $this->isAdmin($user) ||
               $this->isInvoiceCreator($user, $invoice);
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.delete') || 
               $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can generate PDF for the invoice.
     */
    public function generatePdf(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.pdf') || 
               $this->isAdmin($user) ||
               $this->isInvoiceCreator($user, $invoice);
    }

    /**
     * Determine whether the user can manage invoice terms.
     */
    public function manageTerms(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.terms') || 
               $this->isAdmin($user) ||
               $this->isInvoiceCreator($user, $invoice);
    }

    /**
     * Determine whether the user can send invoice.
     */
    public function send(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.send') || 
               $this->isAdmin($user) ||
               $this->isInvoiceCreator($user, $invoice);
    }

    /**
     * Check if user is the invoice creator.
     */
    protected function isInvoiceCreator(User $user, Invoice $invoice): bool
    {
        return $invoice->created_by === $user->id;
    }
}