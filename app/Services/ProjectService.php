<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ProjectService
{
    /**
     * Get all projects with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::with(['client', 'products']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get project by ID with relationships.
     *
     * @param int $id
     * @return Project|null
     */
    public function getProjectById(int $id): ?Project
    {
        return Project::with(['client', 'products', 'invoice'])->find($id);
    }

    /**
     * Get project by ID with full statistics.
     *
     * @param int $id
     * @return array|null
     */
    public function getProjectWithStatistics(int $id): ?array
    {
        $project = $this->getProjectById($id);
        
        if (!$project) {
            return null;
        }

        return [
            'project' => $project,
            'statistics' => $this->calculateProjectStatistics($project),
            'timeline' => $this->getProjectTimeline($project),
            'financial_summary' => $this->getFinancialSummary($project),
        ];
    }

    /**
     * Create a new project.
     *
     * @param array $data
     * @return Project
     * @throws \Exception
     */
    public function createProject(array $data): Project
    {
        DB::beginTransaction();

        try {
            // Validate client exists
            $client = Client::findOrFail($data['client_id']);

            // Create project
            $project = Project::create([
                'client_id' => $data['client_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'value' => $data['value'] ?? 0,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Add products if provided
            if (!empty($data['products'])) {
                $this->attachProducts($project, $data['products']);
            }

            DB::commit();

            Log::info('Project created successfully', ['project_id' => $project->id, 'name' => $project->name]);

            return $project->fresh(['client', 'products']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create project', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Update existing project.
     *
     * @param int $id
     * @param array $data
     * @return Project
     * @throws \Exception
     */
    public function updateProject(int $id, array $data): Project
    {
        $project = Project::findOrFail($id);

        DB::beginTransaction();

        try {
            // Validate date constraints
            $this->validateDateConstraints($data, $project);

            // Update project
            $project->update([
                'name' => $data['name'] ?? $project->name,
                'description' => $data['description'] ?? $project->description,
                'status' => $data['status'] ?? $project->status,
                'value' => $data['value'] ?? $project->value,
                'start_date' => $data['start_date'] ?? $project->start_date,
                'end_date' => $data['end_date'] ?? $project->end_date,
                'notes' => $data['notes'] ?? $project->notes,
            ]);

            // Update products if provided
            if (isset($data['products'])) {
                $this->syncProducts($project, $data['products']);
            }

            DB::commit();

            Log::info('Project updated successfully', ['project_id' => $project->id, 'name' => $project->name]);

            return $project->fresh(['client', 'products']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update project', ['project_id' => $id, 'error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Delete a project.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteProject(int $id): bool
    {
        $project = Project::findOrFail($id);

        DB::beginTransaction();

        try {
            // Check if project has associated invoice
            if ($project->invoice) {
                throw new \Exception('Cannot delete project with associated invoice');
            }

            // Detach all products
            $project->products()->detach();

            // Delete project
            $project->delete();

            DB::commit();

            Log::info('Project deleted successfully', ['project_id' => $id, 'name' => $project->name]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete project', ['project_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Change project status.
     *
     * @param int $id
     * @param string $status
     * @param string|null $notes
     * @return Project
     * @throws \Exception
     */
    public function changeProjectStatus(int $id, string $status, ?string $notes = null): Project
    {
        $project = Project::findOrFail($id);

        // Validate status transition
        $this->validateStatusTransition($project->status, $status);

        DB::beginTransaction();

        try {
            $project->update([
                'status' => $status,
                'notes' => $notes ? $project->notes . "\n\n[Status Change] " . now()->format('Y-m-d H:i:s') . ": " . $notes : $project->notes,
            ]);

            // Handle status-specific logic
            $this->handleStatusChange($project, $status);

            DB::commit();

            Log::info('Project status changed', ['project_id' => $project->id, 'old_status' => $project->getOriginal('status'), 'new_status' => $status]);

            return $project->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change project status', ['project_id' => $id, 'status' => $status, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get projects by client.
     *
     * @param int $clientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getProjectsByClient(int $clientId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::where('client_id', $clientId)->with(['client', 'products']);

        $this->applyFilters($query, $filters);
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get projects by status.
     *
     * @param string $status
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getProjectsByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return Project::byStatus($status)->with(['client', 'products'])->paginate($perPage);
    }

    /**
     * Get overdue projects.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOverdueProjects(int $perPage = 15): LengthAwarePaginator
    {
        return Project::overdue()->with(['client', 'products'])->paginate($perPage);
    }

    /**
     * Get active projects.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getActiveProjects(int $perPage = 15): LengthAwarePaginator
    {
        return Project::active()->with(['client', 'products'])->paginate($perPage);
    }

    /**
     * Add product to project.
     *
     * @param int $projectId
     * @param int $productId
     * @param int $quantity
     * @param float $unitPrice
     * @return Project
     * @throws \Exception
     */
    public function addProductToProject(int $projectId, int $productId, int $quantity, float $unitPrice): Project
    {
        $project = Project::findOrFail($projectId);
        $product = Product::findOrFail($productId);

        DB::beginTransaction();

        try {
            $project->addProduct($product, $quantity, $unitPrice);

            // Recalculate project value
            $newValue = $project->calculateValueFromProducts();
            $project->update(['value' => $newValue]);

            DB::commit();

            Log::info('Product added to project', ['project_id' => $projectId, 'product_id' => $productId, 'quantity' => $quantity]);

            return $project->fresh(['client', 'products']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add product to project', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Remove product from project.
     *
     * @param int $projectId
     * @param int $productId
     * @return Project
     * @throws \Exception
     */
    public function removeProductFromProject(int $projectId, int $productId): Project
    {
        $project = Project::findOrFail($projectId);

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($productId);
            $project->removeProduct($product);

            // Recalculate project value
            $newValue = $project->calculateValueFromProducts();
            $project->update(['value' => $newValue]);

            DB::commit();

            Log::info('Product removed from project', ['project_id' => $projectId, 'product_id' => $productId]);

            return $project->fresh(['client', 'products']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove product from project', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate invoice for project.
     *
     * @param int $projectId
     * @param array $invoiceData
     * @return Invoice
     * @throws \Exception
     */
    public function generateInvoiceForProject(int $projectId, array $invoiceData): Invoice
    {
        $project = Project::findOrFail($projectId);

        if ($project->invoice) {
            throw new \Exception('Project already has an invoice');
        }

        DB::beginTransaction();

        try {
            $invoiceData['project_id'] = $projectId;
            $invoiceData['client_id'] = $project->client_id;
            
            // Generate invoice items from project products
            if (empty($invoiceData['items']) && $project->products->count() > 0) {
                $invoiceData['items'] = $this->generateInvoiceItemsFromProducts($project);
            }

            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->createInvoice($invoiceData);

            DB::commit();

            Log::info('Invoice generated for project', ['project_id' => $projectId, 'invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate invoice for project', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get project statistics.
     *
     * @param Project $project
     * @return array
     */
    public function calculateProjectStatistics(Project $project): array
    {
        $now = Carbon::now();
        $startDate = $project->start_date ? Carbon::parse($project->start_date) : null;
        $endDate = $project->end_date ? Carbon::parse($project->end_date) : null;

        return [
            'total_products' => $project->products()->count(),
            'total_products_value' => $project->calculateValueFromProducts(),
            'progress_percentage' => $project->progress_percentage,
            'duration_in_days' => $project->duration_in_days,
            'days_remaining' => $endDate && $now < $endDate ? $endDate->diffInDays($now) : 0,
            'is_overdue' => $project->isOverdue(),
            'invoice_status' => $project->invoice_status,
            'has_invoice' => !is_null($project->invoice),
            'invoice_amount' => $project->invoice ? $project->invoice->total : 0,
            'invoice_paid' => $project->invoice ? $project->invoice->isPaid() : false,
        ];
    }

    /**
     * Get project timeline.
     *
     * @param Project $project
     * @return array
     */
    protected function getProjectTimeline(Project $project): array
    {
        $timeline = [];

        // Project creation
        $timeline[] = [
            'date' => $project->created_at->format('Y-m-d'),
            'type' => 'created',
            'description' => 'Project created',
            'status' => 'completed',
        ];

        // Status changes
        if ($project->status === 'active' && $project->updated_at > $project->created_at) {
            $timeline[] = [
                'date' => $project->updated_at->format('Y-m-d'),
                'type' => 'status_change',
                'description' => 'Project activated',
                'status' => 'completed',
            ];
        }

        // Invoice generation
        if ($project->invoice) {
            $timeline[] = [
                'date' => $project->invoice->created_at->format('Y-m-d'),
                'type' => 'invoice',
                'description' => 'Invoice generated',
                'status' => 'completed',
            ];
        }

        // Completion
        if ($project->status === 'completed') {
            $timeline[] = [
                'date' => $project->updated_at->format('Y-m-d'),
                'type' => 'completed',
                'description' => 'Project completed',
                'status' => 'completed',
            ];
        }

        return $timeline;
    }

    /**
     * Get financial summary for project.
     *
     * @param Project $project
     * @return array
     */
    protected function getFinancialSummary(Project $project): array
    {
        $productsValue = $project->calculateValueFromProducts();
        $invoiceAmount = $project->invoice ? $project->invoice->total : 0;
        $paidAmount = $project->invoice ? $project->invoice->paid_amount : 0;

        return [
            'products_value' => $productsValue,
            'invoice_amount' => $invoiceAmount,
            'difference' => $invoiceAmount - $productsValue,
            'paid_amount' => $paidAmount,
            'remaining_balance' => $project->invoice ? $project->invoice->remaining_balance : 0,
            'payment_percentage' => $project->invoice ? $project->invoice->payment_percentage : 0,
        ];
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
            'draft' => ['active', 'cancelled'],
            'active' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }
    }

    /**
     * Handle status change logic.
     *
     * @param Project $project
     * @param string $newStatus
     */
    protected function handleStatusChange(Project $project, string $newStatus): void
    {
        switch ($newStatus) {
            case 'active':
                // Set start date if not set
                if (!$project->start_date) {
                    $project->update(['start_date' => now()]);
                }
                break;

            case 'completed':
                // Set end date if not set
                if (!$project->end_date) {
                    $project->update(['end_date' => now()]);
                }
                break;

            case 'cancelled':
                // Handle cancellation logic
                break;
        }
    }

    /**
     * Validate date constraints.
     *
     * @param array $data
     * @param Project $project
     * @throws \Exception
     */
    protected function validateDateConstraints(array $data, Project $project): void
    {
        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : $project->start_date;
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : $project->end_date;

        if ($startDate && $endDate && $endDate < $startDate) {
            throw new \Exception('End date must be after or equal to start date');
        }
    }

    /**
     * Apply filters to project query.
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
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        // Value range filter
        if (!empty($filters['min_value'])) {
            $query->where('value', '>=', $filters['min_value']);
        }

        if (!empty($filters['max_value'])) {
            $query->where('value', '<=', $filters['max_value']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
    }

    /**
     * Attach products to project.
     *
     * @param Project $project
     * @param array $products
     */
    protected function attachProducts(Project $project, array $products): void
    {
        foreach ($products as $productData) {
            $product = Product::find($productData['product_id']);
            if ($product) {
                $project->addProduct(
                    $product,
                    $productData['quantity'] ?? 1,
                    $productData['unit_price'] ?? $product->base_price
                );
            }
        }
    }

    /**
     * Sync products for project.
     *
     * @param Project $project
     * @param array $products
     */
    protected function syncProducts(Project $project, array $products): void
    {
        $syncData = [];
        foreach ($products as $productData) {
            $product = Product::find($productData['product_id']);
            if ($product) {
                $quantity = $productData['quantity'] ?? 1;
                $unitPrice = $productData['unit_price'] ?? $product->base_price;
                $totalPrice = $quantity * $unitPrice;

                $syncData[$product->id] = [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }
        }

        $project->products()->sync($syncData);
    }

    /**
     * Generate invoice items from project products.
     *
     * @param Project $project
     * @return array
     */
    protected function generateInvoiceItemsFromProducts(Project $project): array
    {
        $items = [];

        foreach ($project->products as $product) {
            $items[] = [
                'product_id' => $product->id,
                'description' => $product->name . ' - ' . $product->description,
                'quantity' => $product->pivot->quantity,
                'unit_price' => $product->pivot->unit_price,
                'total_price' => $product->pivot->total_price,
            ];
        }

        return $items;
    }
}