<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTerm;
use App\Models\Client;
use App\Models\Project;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Get all invoices with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::with(['client', 'project', 'items.product', 'terms', 'payments']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get invoice by ID with relationships.
     *
     * @param int $id
     * @return Invoice|null
     */
    public function getInvoiceById(int $id): ?Invoice
    {
        return Invoice::with(['client', 'project', 'items.product', 'terms', 'payments'])->find($id);
    }

    /**
     * Get invoice by ID with full statistics.
     *
     * @param int $id
     * @return array|null
     */
    public function getInvoiceWithStatistics(int $id): ?array
    {
        $invoice = $this->getInvoiceById($id);
        
        if (!$invoice) {
            return null;
        }

        return [
            'invoice' => $invoice,
            'statistics' => $this->calculateInvoiceStatistics($invoice),
            'payment_summary' => $this->getPaymentSummary($invoice),
            'terms_summary' => $this->getTermsSummary($invoice),
        ];
    }

    /**
     * Create a new invoice.
     *
     * @param array $data
     * @return Invoice
     * @throws \Exception
     */
    public function createInvoice(array $data): Invoice
    {
        DB::beginTransaction();

        try {
            // Validate client exists
            $client = Client::findOrFail($data['client_id']);

            // Validate project if provided
            if (!empty($data['project_id'])) {
                $project = Project::findOrFail($data['project_id']);
                if ($project->client_id != $data['client_id']) {
                    throw new \Exception('Project does not belong to the specified client');
                }
            }

            // Generate invoice number if not provided
            if (empty($data['invoice_number'])) {
                $data['invoice_number'] = Invoice::generateInvoiceNumber();
            }

            // Calculate dates
            $issueDate = $data['issue_date'] ?? now();
            $dueDate = $data['due_date'] ?? Carbon::parse($issueDate)->addDays(30);

            // Create invoice
            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'invoice_number' => $data['invoice_number'],
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => 0,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_amount' => 0,
                'total' => 0,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            // Add invoice items
            if (!empty($data['items'])) {
                $this->addInvoiceItems($invoice, $data['items']);
            }

            // Calculate totals
            $invoice->calculateTotals();

            DB::commit();

            Log::info('Invoice created successfully', ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);

            return $invoice->fresh(['client', 'project', 'items.product']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create invoice', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Update existing invoice.
     *
     * @param int $id
     * @param array $data
     * @return Invoice
     * @throws \Exception
     */
    public function updateInvoice(int $id, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($id);

        // Prevent updates to paid invoices
        if ($invoice->isPaid()) {
            throw new \Exception('Cannot update a paid invoice');
        }

        DB::beginTransaction();

        try {
            // Update invoice
            $invoice->update([
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'tax_rate' => $data['tax_rate'] ?? $invoice->tax_rate,
                'notes' => $data['notes'] ?? $invoice->notes,
            ]);

            // Update invoice items if provided
            if (isset($data['items'])) {
                $this->syncInvoiceItems($invoice, $data['items']);
            }

            // Recalculate totals
            $invoice->calculateTotals();

            DB::commit();

            Log::info('Invoice updated successfully', ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);

            return $invoice->fresh(['client', 'project', 'items.product']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update invoice', ['invoice_id' => $id, 'error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Delete an invoice.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteInvoice(int $id): bool
    {
        $invoice = Invoice::findOrFail($id);

        // Prevent deletion of invoices with payments
        if ($invoice->payments()->count() > 0) {
            throw new \Exception('Cannot delete invoice with payments');
        }

        DB::beginTransaction();

        try {
            // Delete related items and terms
            $invoice->items()->delete();
            $invoice->terms()->delete();

            // Delete invoice
            $invoice->delete();

            DB::commit();

            Log::info('Invoice deleted successfully', ['invoice_id' => $id, 'invoice_number' => $invoice->invoice_number]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Change invoice status.
     *
     * @param int $id
     * @param string $status
     * @param string|null $notes
     * @return Invoice
     * @throws \Exception
     */
    public function changeInvoiceStatus(int $id, string $status, ?string $notes = null): Invoice
    {
        $invoice = Invoice::findOrFail($id);

        // Validate status transition
        $this->validateStatusTransition($invoice->status, $status);

        DB::beginTransaction();

        try {
            $invoice->update(['status' => $status]);

            // Handle status-specific logic
            $this->handleStatusChange($invoice, $status);

            DB::commit();

            Log::info('Invoice status changed', ['invoice_id' => $invoice->id, 'old_status' => $invoice->getOriginal('status'), 'new_status' => $status]);

            return $invoice->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change invoice status', ['invoice_id' => $id, 'status' => $status, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate PDF for invoice.
     *
     * @param int $id
     * @return \Barryvdh\DomPDF\PDF
     * @throws \Exception
     */
    public function generateInvoicePdf(int $id)
    {
        $invoice = $this->getInvoiceById($id);

        if (!$invoice) {
            throw new \Exception('Invoice not found');
        }

        try {
            $data = [
                'invoice' => $invoice,
                'client' => $invoice->client,
                'items' => $invoice->items,
                'terms' => $invoice->terms,
                'company' => [
                    'name' => config('app.name', 'Company Name'),
                    'address' => '123 Business Street, City, State 12345',
                    'phone' => '(555) 123-4567',
                    'email' => 'info@company.com',
                ],
            ];

            return Pdf::loadView('invoices.pdf', $data);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send invoice to client.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function sendInvoice(int $id): bool
    {
        $invoice = Invoice::findOrFail($id);

        if (!$invoice->isDraft()) {
            throw new \Exception('Only draft invoices can be sent');
        }

        DB::beginTransaction();

        try {
            // Mark as sent
            $invoice->markAsSent();

            // Generate PDF
            $pdf = $this->generateInvoicePdf($id);

            // Here you would typically send email with PDF attachment
            // For now, we'll just log it
            Log::info('Invoice sent to client', ['invoice_id' => $id, 'client_email' => $invoice->client->email]);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add invoice terms.
     *
     * @param int $invoiceId
     * @param array $termsData
     * @return Collection
     * @throws \Exception
     */
    public function addInvoiceTerms(int $invoiceId, array $termsData): Collection
    {
        $invoice = Invoice::findOrFail($invoiceId);

        // Validate total percentage doesn't exceed 100%
        $totalPercentage = array_sum(array_column($termsData, 'percentage'));
        if ($totalPercentage > 100) {
            throw new \Exception('Total percentage cannot exceed 100%');
        }

        DB::beginTransaction();

        try {
            $terms = collect();
            $totalAmount = $invoice->total;

            foreach ($termsData as $termData) {
                $amount = ($totalAmount * $termData['percentage']) / 100;

                $term = InvoiceTerm::create([
                    'invoice_id' => $invoiceId,
                    'percentage' => $termData['percentage'],
                    'amount' => $amount,
                    'due_date' => $termData['due_date'],
                    'description' => $termData['description'] ?? null,
                    'status' => 'pending',
                ]);

                $terms->push($term);
            }

            DB::commit();

            Log::info('Invoice terms added', ['invoice_id' => $invoiceId, 'terms_count' => $terms->count()]);

            return $terms;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add invoice terms', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get invoices by client.
     *
     * @param int $clientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getInvoicesByClient(int $clientId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::where('client_id', $clientId)->with(['client', 'project', 'items.product', 'terms', 'payments']);

        $this->applyFilters($query, $filters);
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get invoices by status.
     *
     * @param string $status
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getInvoicesByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::byStatus($status)->with(['client', 'project', 'items.product'])->paginate($perPage);
    }

    /**
     * Get overdue invoices.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOverdueInvoices(int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::overdue()->with(['client', 'project'])->paginate($perPage);
    }

    /**
     * Get unpaid invoices.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUnpaidInvoices(int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::whereIn('status', ['sent', 'overdue'])->with(['client', 'project'])->paginate($perPage);
    }

    /**
     * Calculate invoice statistics.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function calculateInvoiceStatistics(Invoice $invoice): array
    {
        return [
            'total_items' => $invoice->items()->count(),
            'total_terms' => $invoice->terms()->count(),
            'total_payments' => $invoice->payments()->count(),
            'paid_terms' => $invoice->terms()->where('status', 'paid')->count(),
            'pending_terms' => $invoice->terms()->where('status', 'pending')->count(),
            'overdue_terms' => $invoice->terms()->where('status', 'overdue')->count(),
            'is_overdue' => $invoice->isOverdue(),
            'days_overdue' => $invoice->isOverdue() ? Carbon::now()->diffInDays($invoice->due_date) : 0,
        ];
    }

    /**
     * Get payment summary for invoice.
     *
     * @param Invoice $invoice
     * @return array
     */
    protected function getPaymentSummary(Invoice $invoice): array
    {
        return [
            'paid_amount' => $invoice->paid_amount,
            'remaining_balance' => $invoice->remaining_balance,
            'payment_percentage' => $invoice->payment_percentage,
            'is_fully_paid' => $invoice->isFullyPaid(),
            'is_partially_paid' => $invoice->isPartiallyPaid(),
        ];
    }

    /**
     * Get terms summary for invoice.
     *
     * @param Invoice $invoice
     * @return array
     */
    protected function getTermsSummary(Invoice $invoice): array
    {
        $terms = $invoice->terms;

        return [
            'total_terms' => $terms->count(),
            'total_percentage' => $terms->sum('percentage'),
            'paid_terms' => $terms->where('status', 'paid')->count(),
            'pending_terms' => $terms->where('status', 'pending')->count(),
            'overdue_terms' => $terms->where('status', 'overdue')->count(),
        ];
    }

    /**
     * Add invoice items.
     *
     * @param Invoice $invoice
     * @param array $items
     */
    protected function addInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            $itemData['invoice_id'] = $invoice->id;
            InvoiceItem::create($itemData);
        }
    }

    /**
     * Sync invoice items.
     *
     * @param Invoice $invoice
     * @param array $items
     */
    protected function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        // Delete existing items
        $invoice->items()->delete();

        // Add new items
        $this->addInvoiceItems($invoice, $items);
    }

    /**
     * Validate status transition.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @throws \Exception
     */
    protected function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $validTransitions = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['paid', 'overdue', 'cancelled'],
            'overdue' => ['paid', 'cancelled'],
            'paid' => [],
            'cancelled' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }
    }

    /**
     * Handle status change logic.
     *
     * @param Invoice $invoice
     * @param string $newStatus
     */
    protected function handleStatusChange(Invoice $invoice, string $newStatus): void
    {
        switch ($newStatus) {
            case 'sent':
                // Update invoice terms status if overdue
                foreach ($invoice->terms as $term) {
                    if ($term->isOverdue()) {
                        $term->markAsOverdue();
                    }
                }
                break;

            case 'overdue':
                // Update invoice terms status
                foreach ($invoice->terms as $term) {
                    if ($term->isPending() && $term->due_date < now()) {
                        $term->markAsOverdue();
                    }
                }
                break;

            case 'paid':
                // Mark all terms as paid
                foreach ($invoice->terms as $term) {
                    if ($term->isPending()) {
                        $term->markAsPaid();
                    }
                }
                break;
        }
    }

    /**
     * Apply filters to invoice query.
     *
     * @param Builder $query
     * @param array $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Client filter
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->where('issue_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('issue_date', '<=', $filters['end_date']);
        }

        // Amount range filter
        if (!empty($filters['min_amount'])) {
            $query->where('total', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('total', '<=', $filters['max_amount']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('client', function ($q) use ($filters) {
                      $q->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }
    }
}