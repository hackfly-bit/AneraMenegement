<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'base_price',
        'unit',
        'sku',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
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
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'base_price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The projects that belong to the product.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_products')
                    ->withPivot('quantity', 'unit_price', 'total_price')
                    ->withTimestamps();
    }

    /**
     * Get the invoice items for the product.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include products by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to search products by name or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%')
              ->orWhere('sku', 'like', '%' . $search . '%');
        });
    }

    /**
     * Check if the product is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Activate the product.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the product.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get the product's current price.
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->base_price;
    }

    /**
     * Calculate price for a specific quantity.
     */
    public function calculatePrice(int $quantity): float
    {
        return $this->base_price * $quantity;
    }

    /**
     * Get the product's usage statistics.
     */
    public function getUsageStatistics(): array
    {
        return [
            'total_projects' => $this->projects()->count(),
            'total_invoices' => $this->invoiceItems()->count(),
            'total_revenue' => $this->invoiceItems()->sum('total_price'),
            'avg_quantity_per_project' => $this->projects()->avg('pivot.quantity') ?? 0,
        ];
    }

    /**
     * Get full product information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'unit' => $this->unit,
            'sku' => $this->sku,
            'is_active' => $this->is_active,
            'current_price' => $this->current_price,
            'usage_statistics' => $this->getUsageStatistics(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($product) {
            // Set default active status if not provided
            if (is_null($product->is_active)) {
                $product->is_active = true;
            }
        });
    }
}