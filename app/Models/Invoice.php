<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'client_id',
        'project_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
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
            'project_id' => 'nullable|exists:projects,id',
            'invoice_number' => 'required|string|max:50|unique:invoices,invoice_number',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'subtotal' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_amount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get the client that owns the invoice.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the project associated with the invoice.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the invoice items for the invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the invoice terms for the invoice.
     */
    public function terms(): HasMany
    {
        return $this->hasMany(InvoiceTerm::class);
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the credit notes for the invoice.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Scope a query to only include invoices by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope a query to only include invoices by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include draft invoices.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include sent invoices.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to only include paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope a query to only include cancelled invoices.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if the invoice is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the invoice is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->isSent() && $this->due_date < now());
    }

    /**
     * Check if the invoice is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the paid amount.
     */
    public function getPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining balance.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->total - $this->paid_amount);
    }

    /**
     * Get the payment percentage.
     */
    public function getPaymentPercentageAttribute(): float
    {
        if ($this->total == 0) {
            return 0;
        }
        
        return min(100, ($this->paid_amount / $this->total) * 100);
    }

    /**
     * Check if the invoice has terms.
     */
    public function hasTerms(): bool
    {
        return $this->terms()->count() > 0;
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total;
    }

    /**
     * Check if the invoice is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->total;
    }

    /**
     * Mark the invoice as sent.
     */
    public function markAsSent(): void
    {
        if ($this->isDraft()) {
            $this->update(['status' => 'sent']);
        }
    }

    /**
     * Mark the invoice as paid.
     */
    public function markAsPaid(): void
    {
        if (in_array($this->status, ['sent', 'overdue'])) {
            $this->update(['status' => 'paid']);
        }
    }

    /**
     * Mark the invoice as overdue.
     */
    public function markAsOverdue(): void
    {
        if ($this->isSent()) {
            $this->update(['status' => 'overdue']);
        }
    }

    /**
     * Mark the invoice as cancelled.
     */
    public function markAsCancelled(): void
    {
        if (!in_array($this->status, ['paid', 'cancelled'])) {
            $this->update(['status' => 'cancelled']);
        }
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        $lastInvoice = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract the number from the last invoice
            $lastNumber = (int) substr($lastInvoice->invoice_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s%s%s%06d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Calculate totals based on items.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total_price');
        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $total = $subtotal + $taxAmount;
        
        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Add an item to the invoice.
     */
    public function addItem(array $itemData): InvoiceItem
    {
        $item = $this->items()->create($itemData);
        $this->calculateTotals();
        
        return $item;
    }

    /**
     * Remove an item from the invoice.
     */
    public function removeItem(InvoiceItem $item): void
    {
        $item->delete();
        $this->calculateTotals();
    }

    /**
     * Get full invoice information including relationships.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => $this->issue_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'status' => $this->status,
            'paid_amount' => $this->paid_amount,
            'remaining_balance' => $this->remaining_balance,
            'payment_percentage' => $this->payment_percentage,
            'notes' => $this->notes,
            'is_overdue' => $this->isOverdue(),
            'is_fully_paid' => $this->isFullyPaid(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'has_terms' => $this->hasTerms(),
            'client' => $this->client,
            'project' => $this->project,
            'items' => $this->items,
            'terms' => $this->terms,
            'payments' => $this->payments,
            'credit_notes' => $this->creditNotes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($invoice) {
            // Generate invoice number if not provided
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateInvoiceNumber();
            }
            
            // Set default status if not provided
            if (empty($invoice->status)) {
                $invoice->status = 'draft';
            }
            
            // Set default tax rate if not provided
            if (is_null($invoice->tax_rate)) {
                $invoice->tax_rate = 0;
            }
            
            // Calculate initial totals
            if (is_null($invoice->subtotal)) {
                $invoice->subtotal = 0;
            }
            if (is_null($invoice->tax_amount)) {
                $invoice->tax_amount = 0;
            }
            if (is_null($invoice->total)) {
                $invoice->total = 0;
            }
        });

        static::updating(function ($invoice) {
            // Update status if overdue
            if ($invoice->isSent() && $invoice->due_date < now()) {
                $invoice->status = 'overdue';
            }
        });
    }
}