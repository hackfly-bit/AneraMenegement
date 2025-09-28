<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Display a listing of the payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'invoice_id' => $request->get('invoice_id'),
            'payment_method' => $request->get('payment_method'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'amount_min' => $request->get('amount_min'),
            'amount_max' => $request->get('amount_max'),
            'sort_by' => $request->get('sort_by', 'payment_date'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $payments = $this->paymentService->getAllPayments($filters, $perPage);

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created payment in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'invoice_term_id' => 'nullable|exists:invoice_terms,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,credit_card,debit_card,check,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validate payment amount doesn't exceed invoice balance
        $invoice = Invoice::find($validated['invoice_id']);
        $remainingBalance = $invoice->total_amount - $invoice->paid_amount;
        
        if ($validated['amount'] > $remainingBalance) {
            return response()->json([
                'message' => 'Payment amount cannot exceed remaining invoice balance',
                'remaining_balance' => $remainingBalance
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payment = $this->paymentService->createPayment($validated);

        return response()->json([
            'data' => $payment,
            'message' => 'Payment created successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified payment.
     *
     * @param Payment $payment
     * @return JsonResponse
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['invoice.client', 'invoiceTerm', 'invoice.project']);

        return response()->json([
            'data' => $payment
        ]);
    }

    /**
     * Update the specified payment in storage.
     *
     * @param Request $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|in:cash,bank_transfer,credit_card,debit_card,check,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // If amount is being updated, validate it doesn't exceed invoice balance
        if (isset($validated['amount']) && $validated['amount'] !== $payment->amount) {
            $invoice = $payment->invoice;
            $currentPaidAmount = $invoice->payments()->where('id', '!=', $payment->id)->sum('amount');
            $remainingBalance = $invoice->total_amount - $currentPaidAmount;
            
            if ($validated['amount'] > $remainingBalance) {
                return response()->json([
                    'message' => 'Payment amount cannot exceed remaining invoice balance',
                    'remaining_balance' => $remainingBalance
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $updatedPayment = $this->paymentService->updatePayment($payment->id, $validated);

        return response()->json([
            'data' => $updatedPayment,
            'message' => 'Payment updated successfully'
        ]);
    }

    /**
     * Remove the specified payment from storage.
     *
     * @param Payment $payment
     * @return JsonResponse
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->paymentService->deletePayment($payment->id);

        return response()->json([
            'message' => 'Payment deleted successfully'
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Get payment statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $statistics = $this->paymentService->getPaymentStatisticsForRange($dateFrom, $dateTo);

        return response()->json([
            'data' => $statistics
        ]);
    }

    /**
     * Get payments by payment method.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function byMethod(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $paymentsByMethod = $this->paymentService->getPaymentsByMethodForRange($dateFrom, $dateTo);

        return response()->json([
            'data' => $paymentsByMethod
        ]);
    }

    /**
     * Process payment refund.
     *
     * @param Request $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01|max:' . $payment->amount,
            'refund_reason' => 'required|string|max:500',
            'refund_date' => 'required|date',
        ]);

        if (!$payment->canBeRefunded()) {
            return response()->json([
                'message' => 'This payment cannot be refunded'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $refundData = [
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'],
            'refund_date' => $validated['refund_date'],
        ];

        $refund = $this->paymentService->processRefund($payment, $refundData);

        return response()->json([
            'data' => $refund,
            'message' => 'Refund processed successfully'
        ]);
    }
}