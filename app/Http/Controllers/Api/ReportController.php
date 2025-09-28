<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected FinancialReportService $reportService;
    protected PdfService $pdfService;

    public function __construct(FinancialReportService $reportService, PdfService $pdfService)
    {
        $this->reportService = $reportService;
        $this->pdfService = $pdfService;
    }

    /**
     * Generate monthly financial report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function monthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . date('Y'),
            'month' => 'required|integer|min:1|max:12',
        ]);

        try {
            $report = $this->reportService->generateMonthlyReport(
                $validated['year'],
                $validated['month']
            );

            return response()->json([
                'data' => $report,
                'message' => 'Monthly report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate monthly report: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate quarterly financial report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function quarterly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . date('Y'),
            'quarter' => 'required|integer|min:1|max:4',
        ]);

        try {
            $report = $this->reportService->generateQuarterlyReport(
                $validated['year'],
                $validated['quarter']
            );

            return response()->json([
                'data' => $report,
                'message' => 'Quarterly report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate quarterly report: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate yearly financial report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function yearly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . date('Y'),
        ]);

        try {
            $report = $this->reportService->generateYearlyReport(
                $validated['year']
            );

            return response()->json([
                'data' => $report,
                'message' => 'Yearly report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate yearly report: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate custom period financial report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function custom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            $report = $this->reportService->generateCustomPeriodReport($startDate, $endDate);

            return response()->json([
                'data' => $report,
                'message' => 'Custom period report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate custom report: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate PDF report.
     *
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:monthly,quarterly,yearly,custom',
            'year' => 'required_if:type,yearly|integer|min:2020|max:' . date('Y'),
            'month' => 'required_if:type,monthly|integer|min:1|max:12',
            'quarter' => 'required_if:type,quarterly|integer|min:1|max:4',
            'start_date' => 'required_if:type,custom|date',
            'end_date' => 'required_if:type,custom|date|after_or_equal:start_date',
        ]);

        try {
            $reportType = $validated['type'];
            
            switch ($reportType) {
                case 'monthly':
                    $report = $this->reportService->generateMonthlyReport(
                        $validated['year'],
                        $validated['month']
                    );
                    $filename = "financial_report_monthly_{$validated['year']}_{$validated['month']}";
                    break;
                
                case 'quarterly':
                    $report = $this->reportService->generateQuarterlyReport(
                        $validated['year'],
                        $validated['quarter']
                    );
                    $filename = "financial_report_quarterly_{$validated['year']}_Q{$validated['quarter']}";
                    break;
                
                case 'yearly':
                    $report = $this->reportService->generateYearlyReport($validated['year']);
                    $filename = "financial_report_yearly_{$validated['year']}";
                    break;
                
                case 'custom':
                    $startDate = Carbon::parse($validated['start_date']);
                    $endDate = Carbon::parse($validated['end_date']);
                    $report = $this->reportService->generateCustomPeriodReport($startDate, $endDate);
                    $filename = "financial_report_custom_{$startDate->format('Y-m-d')}_to_{$endDate->format('Y-m-d')}";
                    break;
            }

            $pdf = $this->pdfService->generateFinancialReportPdf($report);

            return response($pdf, Response::HTTP_OK)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF report: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available report types and parameters.
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => [
                'monthly' => [
                    'description' => 'Monthly financial report',
                    'parameters' => ['year', 'month'],
                    'example' => '/api/reports/monthly?year=2024&month=1'
                ],
                'quarterly' => [
                    'description' => 'Quarterly financial report',
                    'parameters' => ['year', 'quarter'],
                    'example' => '/api/reports/quarterly?year=2024&quarter=1'
                ],
                'yearly' => [
                    'description' => 'Yearly financial report',
                    'parameters' => ['year'],
                    'example' => '/api/reports/yearly?year=2024'
                ],
                'custom' => [
                    'description' => 'Custom period financial report',
                    'parameters' => ['start_date', 'end_date'],
                    'example' => '/api/reports/custom?start_date=2024-01-01&end_date=2024-12-31'
                ]
            ]
        ]);
    }
}