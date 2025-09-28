<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'type',
        'description',
        'account_number',
        'parent_id',
        'is_active',
        'balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
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
            'type' => 'required|in:asset,liability,equity,income,expense',
            'description' => 'nullable|string|max:500',
            'account_number' => 'nullable|string|max:50|unique:accounts,account_number',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
            'balance' => 'nullable|numeric',
        ];
    }

    /**
     * Get the transactions for the account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    /**
     * Get the parent account.
     */
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Get the child accounts.
     */
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include accounts by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include parent accounts.
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include child accounts.
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Check if the account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if the account is a parent account.
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if the account is a child account.
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Check if the account is an asset account.
     */
    public function isAsset(): bool
    {
        return $this->type === 'asset';
    }

    /**
     * Check if the account is a liability account.
     */
    public function isLiability(): bool
    {
        return $this->type === 'liability';
    }

    /**
     * Check if the account is an equity account.
     */
    public function isEquity(): bool
    {
        return $this->type === 'equity';
    }

    /**
     * Check if the account is an income account.
     */
    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    /**
     * Check if the account is an expense account.
     */
    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    /**
     * Get the account type display name.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'income' => 'Income',
            'expense' => 'Expense',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get the current balance of the account.
     */
    public function getCurrentBalanceAttribute(): float
    {
        return $this->balance ?? 0.00;
    }

    /**
     * Get the total income transactions.
     */
    public function getTotalIncomeAttribute(): float
    {
        return $this->transactions()->income()->sum('amount') ?? 0;
    }

    /**
     * Get the total expense transactions.
     */
    public function getTotalExpenseAttribute(): float
    {
        return $this->transactions()->expense()->sum('amount') ?? 0;
    }

    /**
     * Get the net balance from transactions.
     */
    public function getNetBalanceAttribute(): float
    {
        $income = $this->total_income;
        $expense = $this->total_expense;
        
        if ($this->isIncome() || $this->isAsset()) {
            return $income - $expense;
        } else {
            return $expense - $income;
        }
    }

    /**
     * Update the account balance based on transactions.
     */
    public function updateBalance(): void
    {
        $this->update(['balance' => $this->net_balance]);
    }

    /**
     * Add an income transaction to the account.
     */
    public function addIncome(float $amount, string $description, string $date = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'account_id' => $this->id,
            'transaction_type' => 'income',
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date ?? now(),
        ]);
    }

    /**
     * Add an expense transaction to the account.
     */
    public function addExpense(float $amount, string $description, string $date = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'account_id' => $this->id,
            'transaction_type' => 'expense',
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date ?? now(),
        ]);
    }

    /**
     * Get account hierarchy path.
     */
    public function getHierarchyPath(): string
    {
        $path = $this->name;
        $current = $this;
        
        while ($current->parent) {
            $current = $current->parent;
            $path = $current->name . ' > ' . $path;
        }
        
        return $path;
    }

    /**
     * Get all descendant accounts.
     */
    public function getDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    /**
     * Get full account information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_display' => $this->type_display,
            'description' => $this->description,
            'account_number' => $this->account_number,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'balance' => $this->current_balance,
            'total_income' => $this->total_income,
            'total_expense' => $this->total_expense,
            'net_balance' => $this->net_balance,
            'hierarchy_path' => $this->getHierarchyPath(),
            'has_children' => $this->children()->count() > 0,
            'transactions_count' => $this->transactions()->count(),
            'parent' => $this->parent,
            'children' => $this->children,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($account) {
            // Set default active status if not provided
            if (is_null($account->is_active)) {
                $account->is_active = true;
            }
            
            // Set initial balance to 0 if not provided
            if (is_null($account->balance)) {
                $account->balance = 0.00;
            }
        });

        static::deleting(function ($account) {
            // Prevent deletion if account has transactions
            if ($account->transactions()->count() > 0) {
                throw new \Exception('Cannot delete account with existing transactions');
            }
            
            // Prevent deletion if account has children
            if ($account->children()->count() > 0) {
                throw new \Exception('Cannot delete account with child accounts');
            }
        });
    }
}