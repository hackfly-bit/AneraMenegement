<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClientService
{
    /**
     * Get all clients with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllClients(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::query();

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get client by ID with relationships.
     *
     * @param int $id
     * @return Client|null
     */
    public function getClientById(int $id): ?Client
    {
        return Client::with(['projects', 'invoices', 'financeTransactions'])->find($id);
    }

    /**
     * Get client by ID with full statistics.
     *
     * @param int $id
     * @return array|null
     */
    public function getClientWithStatistics(int $id): ?array
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return null;
        }

        return [
            'client' => $client,
            'statistics' => $this->calculateClientStatistics($client),
            'recent_projects' => $client->projects()->latest()->limit(5)->get(),
            'recent_invoices' => $client->invoices()->latest()->limit(5)->get(),
        ];
    }

    /**
     * Create a new client.
     *
     * @param array $data
     * @return Client
     * @throws \Exception
     */
    public function createClient(array $data): Client
    {
        DB::beginTransaction();

        try {
            $client = Client::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'identity_number' => $data['identity_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            DB::commit();

            Log::info('Client created successfully', ['client_id' => $client->id, 'name' => $client->name]);

            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create client', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Update existing client.
     *
     * @param int $id
     * @param array $data
     * @return Client
     * @throws \Exception
     */
    public function updateClient(int $id, array $data): Client
    {
        $client = Client::findOrFail($id);

        DB::beginTransaction();

        try {
            $client->update([
                'name' => $data['name'] ?? $client->name,
                'email' => $data['email'] ?? $client->email,
                'phone' => $data['phone'] ?? $client->phone,
                'address' => $data['address'] ?? $client->address,
                'identity_number' => $data['identity_number'] ?? $client->identity_number,
                'notes' => $data['notes'] ?? $client->notes,
                'status' => $data['status'] ?? $client->status,
            ]);

            DB::commit();

            Log::info('Client updated successfully', ['client_id' => $client->id, 'name' => $client->name]);

            return $client->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update client', ['client_id' => $id, 'error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Delete a client (soft delete).
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteClient(int $id): bool
    {
        $client = Client::findOrFail($id);

        DB::beginTransaction();

        try {
            // Check if client has active projects or unpaid invoices
            if ($this->hasActiveProjects($client)) {
                throw new \Exception('Cannot delete client with active projects');
            }

            if ($this->hasOutstandingInvoices($client)) {
                throw new \Exception('Cannot delete client with outstanding invoices');
            }

            $client->delete();

            DB::commit();

            Log::info('Client deleted successfully', ['client_id' => $id, 'name' => $client->name]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete client', ['client_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Restore a soft-deleted client.
     *
     * @param int $id
     * @return bool
     */
    public function restoreClient(int $id): bool
    {
        $client = Client::onlyTrashed()->findOrFail($id);

        try {
            $client->restore();

            Log::info('Client restored successfully', ['client_id' => $id, 'name' => $client->name]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to restore client', ['client_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Search clients by name or email.
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchClients(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Client::search($search)->paginate($perPage);
    }

    /**
     * Get clients by status.
     *
     * @param string $status
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getClientsByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return Client::byStatus($status)->paginate($perPage);
    }

    /**
     * Get active clients.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getActiveClients(int $perPage = 15): LengthAwarePaginator
    {
        return Client::active()->paginate($perPage);
    }

    /**
     * Get archived clients.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getArchivedClients(int $perPage = 15): LengthAwarePaginator
    {
        return Client::archived()->paginate($perPage);
    }

    /**
     * Get client statistics.
     *
     * @param Client $client
     * @return array
     */
    public function calculateClientStatistics(Client $client): array
    {
        return [
            'total_projects' => $client->projects()->count(),
            'active_projects' => $client->projects()->active()->count(),
            'completed_projects' => $client->projects()->completed()->count(),
            'total_invoices' => $client->invoices()->count(),
            'paid_invoices' => $client->invoices()->paid()->count(),
            'outstanding_invoices' => $client->invoices()->whereIn('status', ['sent', 'overdue'])->count(),
            'total_revenue' => $client->invoices()->paid()->sum('total'),
            'outstanding_balance' => $client->invoices()->whereIn('status', ['sent', 'overdue'])->sum('total'),
            'average_invoice_amount' => $client->invoices()->paid()->avg('total') ?? 0,
            'last_invoice_date' => $client->invoices()->paid()->latest()->value('created_at'),
            'last_project_date' => $client->projects()->latest()->value('created_at'),
        ];
    }

    /**
     * Get top clients by revenue.
     *
     * @param int $limit
     * @return Collection
     */
    public function getTopClientsByRevenue(int $limit = 10): Collection
    {
        return Client::with(['invoices' => function ($query) {
                $query->paid();
            }])
            ->get()
            ->sortByDesc(function ($client) {
                return $client->invoices->sum('total');
            })
            ->take($limit)
            ->values();
    }

    /**
     * Get clients with overdue invoices.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getClientsWithOverdueInvoices(int $perPage = 15): LengthAwarePaginator
    {
        return Client::whereHas('invoices', function (Builder $query) {
                $query->overdue();
            })
            ->withCount(['invoices as overdue_invoices_count' => function (Builder $query) {
                $query->overdue();
            }])
            ->paginate($perPage);
    }

    /**
     * Export clients data.
     *
     * @param array $filters
     * @return Collection
     */
    public function exportClients(array $filters = []): Collection
    {
        $query = Client::query();

        $this->applyFilters($query, $filters);

        return $query->get()->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'status' => $client->status,
                'total_revenue' => $client->total_revenue,
                'outstanding_balance' => $client->outstanding_balance,
                'created_at' => $client->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $client->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Check if client email is unique.
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function isEmailUnique(string $email, ?int $excludeId = null): bool
    {
        $query = Client::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Get client dashboard data.
     *
     * @param int $clientId
     * @return array
     */
    public function getClientDashboardData(int $clientId): array
    {
        $client = $this->getClientById($clientId);

        if (!$client) {
            throw new \Exception('Client not found');
        }

        return [
            'client' => $client,
            'statistics' => $this->calculateClientStatistics($client),
            'recent_activity' => [
                'recent_projects' => $client->projects()->latest()->limit(3)->get(),
                'recent_invoices' => $client->invoices()->latest()->limit(3)->get(),
            ],
            'financial_summary' => [
                'total_revenue' => $client->total_revenue,
                'outstanding_balance' => $client->outstanding_balance,
                'payment_status' => $client->payment_status,
            ],
        ];
    }

    /**
     * Apply filters to client query.
     *
     * @param Builder $query
     * @param array $filters
     * @return void
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Status filter
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
    }

    /**
     * Check if client has active projects.
     *
     * @param Client $client
     * @return bool
     */
    protected function hasActiveProjects(Client $client): bool
    {
        return $client->projects()->active()->exists();
    }

    /**
     * Check if client has outstanding invoices.
     *
     * @param Client $client
     * @return bool
     */
    protected function hasOutstandingInvoices(Client $client): bool
    {
        return $client->invoices()
            ->whereIn('status', ['sent', 'overdue'])
            ->exists();
    }
}