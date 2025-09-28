<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditNote extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',
        'credit_note_number',
        'amount',
        'reason',
        'status',
        'issued_date',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'issued_date' => 'date',
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
            'credit_note_number' => 'required|string|max:50|unique:credit_notes,credit_note_number',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'status' => 'required|in:draft,issued,applied,cancelled',
            'issued_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get the invoice that owns the credit note.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the client through the invoice.
     */
    public function client()
    {
        return $this->invoice->client();
    }

    /**
     * Scope a query to only include credit notes by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include draft credit notes.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include issued credit notes.
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope a query to only include applied credit notes.
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * Scope a query to only include cancelled credit notes.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if the credit note is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the credit note is issued.
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Check if the credit note is applied.
     */
    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    /**
     * Check if the credit note is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the credit note status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'issued' => 'Issued',
            'applied' => 'Applied',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if the credit note can be applied.
     */
    public function canBeApplied(): bool
    {
        return $this->isIssued() && !$this->isCancelled();
    }

    /**
     * Check if the credit note can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'issued']) && !$this->isApplied();
    }

    /**
     * Get the formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, '.', ',');
    }

    /**
     * Get the remaining amount that can be applied.
     */
    public function getRemainingAmountAttribute(): float
    {
        if ($this->isApplied()) {
            return 0;
        }
        
        return $this->amount;
    }

    /**
     * Get the formatted remaining amount.
     */
    public function getFormattedRemainingAmountAttribute(): string
    {
        return number_format($this->remaining_amount, 2, '.', ',');
    }

    /**
     * Issue the credit note.
     */
    public function issue(): void
    {
        if ($this->isDraft()) {
            $this->update([
                'status' => 'issued',
                'issued_date' => now(),
            ]);
        }
    }

    /**
     * Apply the credit note to the invoice.
     */
    public function apply(): void
    {
        if ($this->canBeApplied()) {
            $this->update(['status' => 'applied']);
            
            // Update invoice balance
            $this->invoice->refresh();
            if ($this->invoice->isPaid()) {
                // Handle overpayment or refund
            }
        }
    }

    /**
     * Cancel the credit note.
     */
    public function cancel(): void
    {
        if ($this->canBeCancelled()) {
            $this->update(['status' => 'cancelled']);
        }
    }

    /**
     * Generate a unique credit note number.
     */
    public static function generateCreditNoteNumber(): string
    {
        $prefix = 'CN';
        $year = date('Y');
        $month = date('m');
        
        $lastCreditNote = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastCreditNote) {
            // Extract the number from the last credit note
            $lastNumber = (int) substr($lastCreditNote->credit_note_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s%s%s%06d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Get full credit note information.
     */
    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'credit_note_number' => $this->credit_note_number,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_display' => $this->status_display,
            'issued_date' => $this->issued_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'can_be_applied' => $this->canBeApplied(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'remaining_amount' => $this->remaining_amount,
            'formatted_remaining_amount' => $this->formatted_remaining_amount,
            'invoice' => $this->invoice,
            'client' => $this->client,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($creditNote) {
            // Generate credit note number if not provided
            if (empty($creditNote->credit_note_number)) {
                $creditNote->credit_note_number = self::generateCreditNoteNumber();
            }
            
            // Set default status if not provided
            if (empty($creditNote->status)) {
                $creditNote->status = 'draft';
            }
            
            // Set issued date if status is issued and no date provided
            if ($creditNote->status === 'issued' && !$creditNote->issued_date) {
                $creditNote->issued_date = now();
            }
        });

        static::updating(function ($creditNote) {
            // Set issued date when status changes to issued
            if ($creditNote->isDirty('status') && $creditNote->status === 'issued' && !$creditNote->issued_date) {
                $creditNote->issued_date = now();
            }
        });
    }
}