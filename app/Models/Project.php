<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'client_id',
        'name',
        'description',
        'status',
        'value',
        'start_date',
        'end_date',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,active,completed,cancelled',
            'value' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get the client that owns the project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the invoice associated with the project.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get the attachments for the project.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * The products that belong to the project.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'project_products')
                    ->withPivot('quantity', 'unit_price', 'total_price')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include projects by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope a query to only include projects with specific status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active projects.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed projects.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include overdue projects.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
                    ->where('end_date', '<', now());
    }

    /**
     * Check if the project is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the project is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the project is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the project is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->isActive() && $this->end_date && $this->end_date < now();
    }

    /**
     * Get the project's total value.
     */
    public function getTotalValueAttribute(): float
    {
        return $this->value ?? 0.00;
    }

    /**
     * Get the project's duration in days.
     */
    public function getDurationInDaysAttribute(): ?int
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date);
        }
        
        return null;
    }

    /**
     * Get the project's progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->isCompleted()) {
            return 100.0;
        }
        
        if ($this->isCancelled()) {
            return 0.0;
        }
        
        if (!$this->start_date || !$this->end_date) {
            return 0.0;
        }
        
        $totalDays = $this->duration_in_days;
        $daysPassed = now()->diffInDays($this->start_date);
        
        if ($daysPassed >= $totalDays) {
            return 100.0;
        }
        
        return min(100.0, ($daysPassed / $totalDays) * 100);
    }

    /**
     * Get the project's invoice status.
     */
    public function getInvoiceStatusAttribute(): string
    {
        if (!$this->invoice) {
            return 'no_invoice';
        }
        
        return $this->invoice->status;
    }

    /**
     * Get full project information including relationships.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'value' => $this->value,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'duration_in_days' => $this->duration_in_days,
            'progress_percentage' => $this->progress_percentage,
            'invoice_status' => $this->invoice_status,
            'is_overdue' => $this->isOverdue(),
            'client' => $this->client,
            'products' => $this->products,
            'attachments' => $this->attachments,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($project) {
            // Set default status if not provided
            if (empty($project->status)) {
                $project->status = 'draft';
            }
        });

        static::updating(function ($project) {
            // Validate date constraints
            if ($project->start_date && $project->end_date) {
                if ($project->end_date < $project->start_date) {
                    throw new \Exception('End date must be after or equal to start date');
                }
            }
        });
    }

    /**
     * Mark the project as active.
     */
    public function activate(): void
    {
        if ($this->isDraft()) {
            $this->update(['status' => 'active']);
        }
    }

    /**
     * Mark the project as completed.
     */
    public function complete(): void
    {
        if ($this->isActive()) {
            $this->update(['status' => 'completed']);
        }
    }

    /**
     * Mark the project as cancelled.
     */
    public function cancel(): void
    {
        if (in_array($this->status, ['draft', 'active'])) {
            $this->update(['status' => 'cancelled']);
        }
    }

    /**
     * Add a product to the project.
     */
    public function addProduct(Product $product, int $quantity, float $unitPrice): void
    {
        $totalPrice = $quantity * $unitPrice;
        
        $this->products()->attach($product->id, [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);
    }

    /**
     * Remove a product from the project.
     */
    public function removeProduct(Product $product): void
    {
        $this->products()->detach($product->id);
    }

    /**
     * Calculate the total value based on products.
     */
    public function calculateValueFromProducts(): float
    {
        return $this->products->sum(function ($product) {
            return $product->pivot->total_price;
        });
    }
}