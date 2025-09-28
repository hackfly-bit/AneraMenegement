<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;
    protected PdfService $pdfService;

    public function __construct(InvoiceService $invoiceService, PdfService $pdfService)
    {
        $this->invoiceService = $invoiceService;
        $this->pdfService = $pdfService;
    }

    /**
     * Display a listing of the invoices.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'client_id' => $request->get('client_id'),
            'project_id' => $request->get('project_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'total_min' => $request->get('total_min'),
            'total_max' => $request->get('total_max'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        $invoices = $this->invoiceService->getInvoices($filters);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created invoice in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        $invoice = $this->invoiceService->createInvoice($validated);

        return response()->json([
            'data' => $invoice->load(['client', 'project', 'items']),
            'message' => 'Invoice created successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified invoice.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['client', 'project', 'items', 'payments', 'terms']);

        return response()->json([
            'data' => $invoice
        ]);
    }

    /**
     * Update the specified invoice in storage.
     *
     * @param Request $request
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft invoices can be updated'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'client_id' => 'sometimes|required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'invoice_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date|after_or_equal:invoice_date',
            'items' => 'sometimes|required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        $updatedInvoice = $this->invoiceService->updateInvoice($invoice, $validated);

        return response()->json([
            'data' => $updatedInvoice->load(['client', 'project', 'items']),
            'message' => 'Invoice updated successfully'
        ]);
    }

    /**
     * Remove the specified invoice from storage.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft invoices can be deleted'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->invoiceService->deleteInvoice($invoice);

        return response()->json([
            'message' => 'Invoice deleted successfully'
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Generate PDF for the specified invoice.
     *
     * @param Invoice $invoice
     * @return JsonResponse|Response
     */
    public function pdf(Invoice $invoice)
    {
        $invoice->load(['client', 'project', 'items']);
        
        try {
            $pdf = $this->pdfService->generateInvoicePdf($invoice);
            
            return response($pdf, Response::HTTP_OK)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="invoice_' . $invoice->invoice_number . '.pdf"');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Send invoice to client via email.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function send(Invoice $invoice): JsonResponse
    {
        $invoice->load('client');
        
        try {
            $this->invoiceService->sendInvoice($invoice);
            
            return response()->json([
                'message' => 'Invoice sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send invoice: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark invoice as paid.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function markAsPaid(Invoice $invoice): JsonResponse
    {
        try {
            $updatedInvoice = $this->invoiceService->markAsPaid($invoice);
            
            return response()->json([
                'data' => $updatedInvoice,
                'message' => 'Invoice marked as paid'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Mark invoice as cancelled.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function cancel(Invoice $invoice): JsonResponse
    {
        try {
            $updatedInvoice = $this->invoiceService->cancelInvoice($invoice);
            
            return response()->json([
                'data' => $updatedInvoice,
                'message' => 'Invoice cancelled'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Add payment terms to invoice.
     *
     * @param Request $request
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function addTerms(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'terms' => 'required|array|min:1',
            'terms.*.description' => 'required|string|max:255',
            'terms.*.amount' => 'required|numeric|min:0.01',
            'terms.*.due_date' => 'required|date',
        ]);

        $terms = $this->invoiceService->addInvoiceTerms($invoice, $validated['terms']);

        return response()->json([
            'data' => $terms,
            'message' => 'Payment terms added successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Get invoice statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $stats = $this->invoiceService->getInvoiceStats();

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get invoices by client.
     *
     * @param Request $request
     * @param int $clientId
     * @return JsonResponse
     */
    public function byClient(Request $request, int $clientId): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'per_page' => $request->get('per_page', 15),
        ];

        $invoices = $this->invoiceService->getInvoicesByClient($clientId, $filters);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ]
        ]);
    }
}