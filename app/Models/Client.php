<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'identity_number',
        'notes',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'identity_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:active,archived',
        ];
    }

    /**
     * Validation rules for updating the model.
     *
     * @param int|null $clientId
     * @return array<string, string>
     */
    public static function updateRules(?int $clientId = null): array
    {
        $rules = self::rules();
        
        // Allow same email for current client
        if ($clientId) {
            $rules['email'] = 'required|email|unique:clients,email,' . $clientId . '|max:255';
        }
        
        return $rules;
    }

    /**
     * Get the projects for the client.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the invoices for the client.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the payments for the client through invoices.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    /**
     * Get the finance transactions for the client.
     */
    public function financeTransactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    /**
     * Scope a query to only include active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include archived clients.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to search clients by name or email.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('email', 'like', '%' . $search . '%');
        });
    }

    /**
     * Get the client's total revenue.
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->sum('total');
    }

    /**
     * Get the client's outstanding balance.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['sent', 'overdue'])
            ->sum('total');
    }

    /**
     * Get the client's payment status.
     */
    public function getPaymentStatusAttribute(): string
    {
        $outstanding = $this->outstanding_balance;
        
        if ($outstanding > 0) {
            return 'has_outstanding';
        }
        
        return 'paid_up';
    }

    /**
     * Check if the client is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the client is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Archive the client.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Activate the client.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Get full client information including statistics.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'identity_number' => $this->identity_number,
            'notes' => $this->notes,
            'status' => $this->status,
            'total_revenue' => $this->total_revenue,
            'outstanding_balance' => $this->outstanding_balance,
            'payment_status' => $this->payment_status,
            'projects_count' => $this->projects()->count(),
            'invoices_count' => $this->invoices()->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($client) {
            // Set default status if not provided
            if (empty($client->status)) {
                $client->status = 'active';
            }
        });

        static::deleting(function ($client) {
            // Soft delete related projects
            $client->projects()->each(function ($project) {
                $project->delete();
            });
        });

        static::restoring(function ($client) {
            // Restore related projects
            $client->projects()->onlyTrashed()->each(function ($project) {
                $project->restore();
            });
        });
    }
}