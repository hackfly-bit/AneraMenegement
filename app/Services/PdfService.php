<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Invoice;
use Carbon\Carbon;

class PdfService
{
    /**
     * Generate invoice PDF.
     *
     * @param Invoice $invoice
     * @return string
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $data = [
            'invoice' => $invoice,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data);
        return $pdf->output();
    }

    /**
     * Generate financial report PDF.
     *
     * @param array $reportData
     * @return string
     */
    public function generateFinancialReportPdf(array $reportData): string
    {
        $data = [
            'report' => $reportData,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $pdf = Pdf::loadView('pdf.financial-report', $data);
        return $pdf->output();
    }

    /**
     * Generate payment receipt PDF.
     *
     * @param \App\Models\Payment $payment
     * @return string
     */
    public function generatePaymentReceiptPdf($payment): string
    {
        $data = [
            'payment' => $payment,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        $pdf = Pdf::loadView('pdf.payment-receipt', $data);
        return $pdf->output();
    }
}