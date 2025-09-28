<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinanceTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'account_id',
        'transaction_type',
        'amount',
        'description',
        'transaction_date',
        'reference_number',
        'client_id',
        'project_id',
        'invoice_id',
        'payment_id',
        'transactionable_type',
        'transactionable_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
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
            'account_id' => 'required|exists:accounts,id',
            'transaction_type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_id' => 'nullable|exists:payments,id',
            'transactionable_type' => 'nullable|string|max:100',
            'transactionable_id' => 'nullable|integer',
        ];
    }

    /**
     * Get the account that owns the transaction.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the client that owns the transaction.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the project that owns the transaction.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the invoice that owns the transaction.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the payment that owns the transaction.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the parent transactionable model.
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include income transactions.
     */
    public function scopeIncome($query)
    {
        return $query->where('transaction_type', 'income');
    }

    /**
     * Scope a query to only include expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->where('transaction_type', 'expense');
    }

    /**
     * Scope a query to only include transactions by account.
     */
    public function scopeByAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope a query to only include transactions by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope a query to only include transactions by project.
     */
    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope a query to only include transactions within date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Check if the transaction is income.
     */
    public function isIncome(): bool
    {
        return $this->transaction_type === 'income';
    }

    /**
     * Check if the transaction is expense.
     */
    public function isExpense(): bool
    {
        return $this->transaction_type === 'expense';
    }

    /**
     * Get the transaction amount with sign.
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isIncome() ? $this->amount : -$this->amount;
    }

    /**
     * Get the transaction type display name.
     */
    public function getTypeDisplayAttribute(): string
    {
        return $this->isIncome() ? 'Income' : 'Expense';
    }

    /**
     * Check if the transaction is related to a client.
     */
    public function isClientRelated(): bool
    {
        return !is_null($this->client_id);
    }

    /**
     * Check if the transaction is related to a project.
     */
    public function isProjectRelated(): bool
    {
        return !is_null($this->project_id);
    }

    /**
     * Check if the transaction is related to an invoice.
     */
    public function isInvoiceRelated(): bool
    {
        return !is_null($this->invoice_id);
    }

    /**
     * Check if the transaction is related to a payment.
     */
    public function isPaymentRelated(): bool
    {
        return !is_null($this->payment_id);
    }

    /**
     * Get the transaction category based on account.
     */
    public function getCategoryAttribute(): string
    {
        return $this->account ? $this->account->name : 'Uncategorized';
    }

    /**
     * Get the transaction balance impact.
     */
    public function getBalanceImpactAttribute(): float
    {
        return $this->signed_amount;
    }

    /**
     * Get full transaction information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'transaction_type' => $this->transaction_type,
            'type_display' => $this->type_display,
            'amount' => $this->amount,
            'signed_amount' => $this->signed_amount,
            'balance_impact' => $this->balance_impact,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'reference_number' => $this->reference_number,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'invoice_id' => $this->invoice_id,
            'payment_id' => $this->payment_id,
            'category' => $this->category,
            'is_client_related' => $this->isClientRelated(),
            'is_project_related' => $this->isProjectRelated(),
            'is_invoice_related' => $this->isInvoiceRelated(),
            'is_payment_related' => $this->isPaymentRelated(),
            'account' => $this->account,
            'client' => $this->client,
            'project' => $this->project,
            'invoice' => $this->invoice,
            'payment' => $this->payment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Create a transaction from a payment.
     */
    public static function createFromPayment(Payment $payment): self
    {
        $invoice = $payment->invoice;
        
        return self::create([
            'account_id' => self::getDefaultIncomeAccountId(),
            'transaction_type' => 'income',
            'amount' => $payment->amount,
            'description' => 'Payment received for invoice ' . $invoice->invoice_number,
            'transaction_date' => $payment->payment_date,
            'client_id' => $invoice->client_id,
            'project_id' => $invoice->project_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Create a transaction from an expense.
     */
    public static function createExpense(array $data): self
    {
        return self::create(array_merge($data, [
            'transaction_type' => 'expense',
        ]));
    }

    /**
     * Get default income account ID.
     */
    private static function getDefaultIncomeAccountId(): int
    {
        // This should be configurable, for now return first income account
        $account = Account::where('type', 'income')->first();
        return $account ? $account->id : 1;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            // Set transaction date to today if not provided
            if (empty($transaction->transaction_date)) {
                $transaction->transaction_date = now();
            }
            
            // Generate reference number if not provided
            if (empty($transaction->reference_number)) {
                $transaction->reference_number = 'TXN-' . strtoupper(Str::random(8));
            }
        });

        static::created(function ($transaction) {
            // Update account balance
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }
        });

        static::deleted(function ($transaction) {
            // Update account balance
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }
        });
    }
}