<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceTerm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',
        'percentage',
        'amount',
        'due_date',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'due_date' => 'date',
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
            'invoice_id' => 'required|exists:invoices,id',
            'percentage' => 'required|numeric|min:0.01|max:100',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:pending,paid,overdue,cancelled',
        ];
    }

    /**
     * Get the invoice that owns the term.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the payments for the term.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if the term is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the term is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the term is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->isPending() && $this->due_date < now());
    }

    /**
     * Check if the term is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the paid amount for this term.
     */
    public function getPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining balance for this term.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    /**
     * Check if the term is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->amount;
    }

    /**
     * Check if the term is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->amount;
    }

    /**
     * Mark the term as paid.
     */
    public function markAsPaid(): void
    {
        if ($this->isPending()) {
            $this->update(['status' => 'paid']);
        }
    }

    /**
     * Mark the term as overdue.
     */
    public function markAsOverdue(): void
    {
        if ($this->isPending()) {
            $this->update(['status' => 'overdue']);
        }
    }

    /**
     * Mark the term as cancelled.
     */
    public function markAsCancelled(): void
    {
        if (in_array($this->status, ['pending', 'overdue'])) {
            $this->update(['status' => 'cancelled']);
        }
    }

    /**
     * Record a payment for this term.
     */
    public function recordPayment(float $amount, string $paymentDate, string $method, string $reference = null, string $notes = null): Payment
    {
        $payment = $this->payments()->create([
            'invoice_id' => $this->invoice_id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_method' => $method,
            'reference_number' => $reference,
            'notes' => $notes,
        ]);

        // Update term status
        if ($this->isFullyPaid()) {
            $this->markAsPaid();
        }

        // Update invoice status
        $this->invoice->refresh();
        if ($this->invoice->isFullyPaid()) {
            $this->invoice->markAsPaid();
        } elseif ($this->invoice->isPartiallyPaid()) {
            // Keep current status or update to partially_paid if needed
        }

        return $payment;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($term) {
            // Set default status if not provided
            if (empty($term->status)) {
                $term->status = 'pending';
            }
        });

        static::updating(function ($term) {
            // Update status if overdue
            if ($term->isPending() && $term->due_date < now()) {
                $term->status = 'overdue';
            }
        });
    }
}