<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',
        'invoice_term_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
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
            'invoice_term_id' => 'nullable|exists:invoice_terms,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,credit_card,debit_card,check,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get the invoice that owns the payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the invoice term that owns the payment.
     */
    public function invoiceTerm(): BelongsTo
    {
        return $this->belongsTo(InvoiceTerm::class);
    }

    /**
     * Get the client through the invoice.
     */
    public function client()
    {
        return $this->invoice->client();
    }

    /**
     * Scope a query to only include payments by invoice.
     */
    public function scopeByInvoice($query, int $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope a query to only include payments by method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope a query to only include payments within date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include payments by invoice term.
     */
    public function scopeByInvoiceTerm($query, int $invoiceTermId)
    {
        return $query->where('invoice_term_id', $invoiceTermId);
    }

    /**
     * Check if the payment is for a specific invoice term.
     */
    public function isForInvoiceTerm(): bool
    {
        return !is_null($this->invoice_term_id);
    }

    /**
     * Get the payment method display name.
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'check' => 'Check',
            'other' => 'Other',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Check if the payment has a reference number.
     */
    public function hasReferenceNumber(): bool
    {
        return !empty($this->reference_number);
    }

    /**
     * Get the payment status based on invoice.
     */
    public function getStatusAttribute(): string
    {
        return $this->invoice->status;
    }

    /**
     * Get the payment amount in formatted currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, '.', ',');
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->invoice->status, ['paid', 'partially_paid']);
    }

    /**
     * Get payment summary information.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_term_id' => $this->invoice_term_id,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'payment_method_display' => $this->payment_method_display,
            'reference_number' => $this->reference_number,
            'has_reference_number' => $this->hasReferenceNumber(),
            'notes' => $this->notes,
            'status' => $this->status,
            'can_be_refunded' => $this->canBeRefunded(),
            'is_for_invoice_term' => $this->isForInvoiceTerm(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($payment) {
            // Validate that invoice term belongs to the same invoice
            if ($payment->invoice_term_id) {
                $invoiceTerm = InvoiceTerm::find($payment->invoice_term_id);
                if ($invoiceTerm && $invoiceTerm->invoice_id !== $payment->invoice_id) {
                    throw new \Exception('Invoice term must belong to the same invoice');
                }
            }
        });

        static::created(function ($payment) {
            // Update invoice and invoice term statuses
            $payment->invoice->refresh();
            
            if ($payment->invoice->isFullyPaid()) {
                $payment->invoice->markAsPaid();
            }
            
            if ($payment->invoice_term_id) {
                $payment->invoiceTerm->refresh();
                if ($payment->invoiceTerm->isFullyPaid()) {
                    $payment->invoiceTerm->markAsPaid();
                }
            }
        });

        static::deleted(function ($payment) {
            // Update invoice and invoice term statuses
            $payment->invoice->refresh();
            
            if ($payment->invoice_term_id) {
                $payment->invoiceTerm->refresh();
            }
        });
    }
}