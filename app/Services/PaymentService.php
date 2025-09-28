<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceTerm;
use App\Models\FinanceTransaction;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Get all payments with optional filtering and pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::with(['invoice.client', 'invoiceTerm']);

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($filters['sort_by'] ?? 'payment_date', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get payment by ID with relationships.
     *
     * @param int $id
     * @return Payment|null
     */
    public function getPaymentById(int $id): ?Payment
    {
        return Payment::with(['invoice.client', 'invoiceTerm'])->find($id);
    }

    /**
     * Get payment by ID with full details.
     *
     * @param int $id
     * @return array|null
     */
    public function getPaymentWithDetails(int $id): ?array
    {
        $payment = $this->getPaymentById($id);
        
        if (!$payment) {
            return null;
        }

        return [
            'payment' => $payment,
            'related_invoice' => $payment->invoice,
            'related_term' => $payment->invoiceTerm,
            'client_info' => $payment->invoice ? $payment->invoice->client : null,
        ];
    }

    /**
     * Create a new payment.
     *
     * @param array $data
     * @return Payment
     * @throws \Exception
     */
    public function createPayment(array $data): Payment
    {
        DB::beginTransaction();

        try {
            // Validate invoice exists
            $invoice = Invoice::findOrFail($data['invoice_id']);

            // Validate invoice term if provided
            if (!empty($data['invoice_term_id'])) {
                $invoiceTerm = InvoiceTerm::findOrFail($data['invoice_term_id']);
                if ($invoiceTerm->invoice_id != $data['invoice_id']) {
                    throw new \Exception('Invoice term does not belong to the specified invoice');
                }
            }

            // Validate payment amount
            if ($data['amount'] <= 0) {
                throw new \Exception('Payment amount must be greater than zero');
            }

            // Check if payment exceeds invoice balance
            $remainingBalance = $invoice->remaining_balance;
            if ($data['amount'] > $remainingBalance) {
                throw new \Exception('Payment amount exceeds invoice remaining balance');
            }

            // Create payment
            $payment = Payment::create([
                'invoice_id' => $data['invoice_id'],
                'invoice_term_id' => $data['invoice_term_id'] ?? null,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'] ?? now(),
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create finance transaction
            $this->createFinanceTransaction($payment);

            // Update invoice status
            $invoice->refresh();
            if ($invoice->isFullyPaid()) {
                $invoice->markAsPaid();
            }

            // Update invoice term status if applicable
            if ($payment->invoiceTerm && $payment->invoiceTerm->isFullyPaid()) {
                $payment->invoiceTerm->markAsPaid();
            }

            DB::commit();

            Log::info('Payment created successfully', ['payment_id' => $payment->id, 'amount' => $payment->amount]);

            return $payment->fresh(['invoice.client', 'invoiceTerm']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Update existing payment.
     *
     * @param int $id
     * @param array $data
     * @return Payment
     * @throws \Exception
     */
    public function updatePayment(int $id, array $data): Payment
    {
        $payment = Payment::findOrFail($id);

        // Prevent updates to payments that are part of a reconciled invoice
        if ($payment->invoice->isPaid()) {
            throw new \Exception('Cannot update payment for a paid invoice');
        }

        DB::beginTransaction();

        try {
            // Validate invoice if changed
            if (isset($data['invoice_id']) && $data['invoice_id'] != $payment->invoice_id) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                
                // Check if payment amount exceeds new invoice balance
                $remainingBalance = $invoice->remaining_balance + $payment->amount;
                if ($data['amount'] > $remainingBalance) {
                    throw new \Exception('Payment amount exceeds invoice remaining balance');
                }
            }

            // Validate payment amount
            if (isset($data['amount']) && $data['amount'] <= 0) {
                throw new \Exception('Payment amount must be greater than zero');
            }

            // Update payment
            $payment->update([
                'amount' => $data['amount'] ?? $payment->amount,
                'payment_date' => $data['payment_date'] ?? $payment->payment_date,
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'reference_number' => $data['reference_number'] ?? $payment->reference_number,
                'notes' => $data['notes'] ?? $payment->notes,
            ]);

            // Update finance transaction
            $this->updateFinanceTransaction($payment);

            // Update invoice status
            $payment->invoice->refresh();
            if ($payment->invoice->isFullyPaid()) {
                $payment->invoice->markAsPaid();
            }

            DB::commit();

            Log::info('Payment updated successfully', ['payment_id' => $payment->id, 'amount' => $payment->amount]);

            return $payment->fresh(['invoice.client', 'invoiceTerm']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update payment', ['payment_id' => $id, 'error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Delete a payment.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deletePayment(int $id): bool
    {
        $payment = Payment::findOrFail($id);

        // Prevent deletion of payments that are part of a reconciled invoice
        if ($payment->invoice->isPaid()) {
            throw new \Exception('Cannot delete payment for a paid invoice');
        }

        DB::beginTransaction();

        try {
            // Delete finance transaction
            $this->deleteFinanceTransaction($payment);

            // Delete payment
            $payment->delete();

            // Update invoice status
            $payment->invoice->refresh();
            if (!$payment->invoice->isFullyPaid()) {
                // Reset invoice status if it was marked as paid
                if ($payment->invoice->status === 'paid') {
                    $payment->invoice->update(['status' => 'sent']);
                }
            }

            DB::commit();

            Log::info('Payment deleted successfully', ['payment_id' => $id]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete payment', ['payment_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get payments by invoice.
     *
     * @param int $invoiceId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaymentsByInvoice(int $invoiceId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::where('invoice_id', $invoiceId)->with(['invoice.client', 'invoiceTerm']);

        $this->applyFilters($query, $filters);
        $query->orderBy($filters['sort_by'] ?? 'payment_date', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get payments by method.
     *
     * @param string $method
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaymentsByMethod(string $method, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::where('payment_method', $method)->with(['invoice.client', 'invoiceTerm']);

        $this->applyFilters($query, $filters);
        $query->orderBy($filters['sort_by'] ?? 'payment_date', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get payments by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaymentsByDateRange(string $startDate, string $endDate, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::whereBetween('payment_date', [$startDate, $endDate])->with(['invoice.client', 'invoiceTerm']);

        $this->applyFilters($query, $filters);
        $query->orderBy($filters['sort_by'] ?? 'payment_date', $filters['sort_order'] ?? 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get payment statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        $query = Payment::query();
        $this->applyFilters($query, $filters);

        $totalPayments = $query->count();
        $totalAmount = $query->sum('amount');
        $averageAmount = $totalPayments > 0 ? $totalAmount / $totalPayments : 0;

        $methodStats = Payment::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method,
                    'count' => $item->count,
                    'total' => $item->total,
                    'average' => $item->count > 0 ? $item->total / $item->count : 0,
                ];
            });

        return [
            'total_payments' => $totalPayments,
            'total_amount' => $totalAmount,
            'average_amount' => $averageAmount,
            'method_statistics' => $methodStats,
            'daily_totals' => $this->getDailyPaymentTotals($filters),
            'monthly_totals' => $this->getMonthlyPaymentTotals($filters),
        ];
    }

    /**
     * Process bulk payment.
     *
     * @param array $paymentsData
     * @return Collection
     * @throws \Exception
     */
    public function processBulkPayments(array $paymentsData): Collection
    {
        $payments = collect();

        DB::beginTransaction();

        try {
            foreach ($paymentsData as $paymentData) {
                $payment = $this->createPayment($paymentData);
                $payments->push($payment);
            }

            DB::commit();

            Log::info('Bulk payments processed successfully', ['count' => $payments->count(), 'total_amount' => $payments->sum('amount')]);

            return $payments;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process bulk payments', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reconcile payment.
     *
     * @param int $paymentId
     * @param string $referenceNumber
     * @param string|null $notes
     * @return Payment
     * @throws \Exception
     */
    public function reconcilePayment(int $paymentId, string $referenceNumber, ?string $notes = null): Payment
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->reference_number) {
            throw new \Exception('Payment is already reconciled');
        }

        DB::beginTransaction();

        try {
            $payment->update([
                'reference_number' => $referenceNumber,
                'notes' => $notes ? $payment->notes . "\n\n[Reconciled] " . now()->format('Y-m-d H:i:s') . ": " . $notes : $payment->notes,
            ]);

            DB::commit();

            Log::info('Payment reconciled successfully', ['payment_id' => $paymentId, 'reference_number' => $referenceNumber]);

            return $payment->fresh(['invoice.client', 'invoiceTerm']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reconcile payment', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get payment trends.
     *
     * @param int $days
     * @return Collection
     */
    public function getPaymentTrends(int $days = 30): Collection
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        return Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'total' => $item->total,
                    'average' => $item->count > 0 ? $item->total / $item->count : 0,
                ];
            });
    }

    /**
     * Get payment statistics for date range.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function getPaymentStatisticsForRange(string $dateFrom, string $dateTo): array
    {
        $filters = [
            'start_date' => $dateFrom,
            'end_date' => $dateTo,
        ];

        return $this->getPaymentStatistics($filters);
    }

    /**
     * Get payments grouped by method for date range.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return Collection
     */
    public function getPaymentsByMethodForRange(string $dateFrom, string $dateTo): Collection
    {
        return Payment::whereBetween('payment_date', [$dateFrom, $dateTo])
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
                DB::raw('AVG(amount) as average')
            )
            ->groupBy('payment_method')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method,
                    'method_display' => $this->getPaymentMethodDisplay($item->payment_method),
                    'count' => $item->count,
                    'total' => $item->total,
                    'average' => $item->average,
                ];
            });
    }

    /**
     * Process payment refund.
     *
     * @param Payment $payment
     * @param array $refundData
     * @return array
     * @throws \Exception
     */
    public function processRefund(Payment $payment, array $refundData): array
    {
        if (!$payment->canBeRefunded()) {
            throw new \Exception('This payment cannot be refunded');
        }

        DB::beginTransaction();

        try {
            $refundAmount = $refundData['refund_amount'];
            $refundReason = $refundData['refund_reason'];
            $refundDate = $refundData['refund_date'];

            // Create refund record (negative payment)
            $refund = Payment::create([
                'invoice_id' => $payment->invoice_id,
                'invoice_term_id' => $payment->invoice_term_id,
                'amount' => -abs($refundAmount), // Negative amount for refund
                'payment_date' => $refundDate,
                'payment_method' => $payment->payment_method,
                'reference_number' => 'REFUND-' . uniqid(),
                'notes' => "Refund for payment {$payment->id}: {$refundReason}",
            ]);

            // Create finance transaction for refund
            $this->createRefundFinanceTransaction($refund);

            // Update invoice status
            $payment->invoice->refresh();
            if (!$payment->invoice->isFullyPaid()) {
                $payment->invoice->update(['status' => 'partially_paid']);
            }

            DB::commit();

            Log::info('Payment refund processed successfully', [
                'original_payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'refund_amount' => $refundAmount
            ]);

            return [
                'refund' => $refund,
                'original_payment' => $payment,
                'refund_amount' => $refundAmount,
                'remaining_balance' => $payment->invoice->remaining_balance,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payment refund', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get payment method display name.
     *
     * @param string $method
     * @return string
     */
    protected function getPaymentMethodDisplay(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'check' => 'Check',
            'other' => 'Other',
            default => ucfirst($method),
        };
    }

    /**
     * Create finance transaction for refund.
     *
     * @param Payment $refund
     * @return FinanceTransaction
     */
    protected function createRefundFinanceTransaction(Payment $refund): FinanceTransaction
    {
        $account = Account::where('type', 'expense')->first();
        if (!$account) {
            throw new \Exception('Expense account not found for refund');
        }

        return FinanceTransaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => abs($refund->amount), // Positive amount for expense
            'description' => "Refund processed for invoice {$refund->invoice->invoice_number}",
            'transaction_date' => $refund->payment_date,
            'client_id' => $refund->invoice->client_id,
            'project_id' => $refund->invoice->project_id,
            'invoice_id' => $refund->invoice_id,
            'payment_id' => $refund->id,
        ]);
    }

    /**
     * Create finance transaction for payment.
     *
     * @param Payment $payment
     * @return FinanceTransaction
     */
    protected function createFinanceTransaction(Payment $payment): FinanceTransaction
    {
        $account = Account::where('type', 'income')->first();
        if (!$account) {
            throw new \Exception('Income account not found');
        }

        return FinanceTransaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'income',
            'amount' => $payment->amount,
            'description' => "Payment received for invoice {$payment->invoice->invoice_number}",
            'transaction_date' => $payment->payment_date,
            'client_id' => $payment->invoice->client_id,
            'project_id' => $payment->invoice->project_id,
            'invoice_id' => $payment->invoice_id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Update finance transaction for payment.
     *
     * @param Payment $payment
     * @return FinanceTransaction
     */
    protected function updateFinanceTransaction(Payment $payment): FinanceTransaction
    {
        $transaction = FinanceTransaction::where('payment_id', $payment->id)->first();
        if (!$transaction) {
            return $this->createFinanceTransaction($payment);
        }

        $transaction->update([
            'amount' => $payment->amount,
            'transaction_date' => $payment->payment_date,
            'description' => "Payment received for invoice {$payment->invoice->invoice_number}",
        ]);

        return $transaction;
    }

    /**
     * Delete finance transaction for payment.
     *
     * @param Payment $payment
     * @return void
     */
    protected function deleteFinanceTransaction(Payment $payment): void
    {
        FinanceTransaction::where('payment_id', $payment->id)->delete();
    }

    /**
     * Get daily payment totals.
     *
     * @param array $filters
     * @return Collection
     */
    protected function getDailyPaymentTotals(array $filters): Collection
    {
        $query = Payment::query();
        $this->applyFilters($query, $filters);

        return $query->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get monthly payment totals.
     *
     * @param array $filters
     * @return Collection
     */
    protected function getMonthlyPaymentTotals(array $filters): Collection
    {
        $query = Payment::query();
        $this->applyFilters($query, $filters);

        return $query->select(
                DB::raw('DATE_FORMAT(payment_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Apply filters to payment query.
     *
     * @param Builder $query
     * @param array $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Invoice filter
        if (!empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        // Method filter
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->where('payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('payment_date', '<=', $filters['end_date']);
        }

        // Amount range filter
        if (!empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('notes', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('invoice', function ($q) use ($filters) {
                      $q->where('invoice_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('client', function ($q) use ($filters) {
                            $q->where('name', 'like', '%' . $filters['search'] . '%');
                        });
                  });
            });
        }
    }
}