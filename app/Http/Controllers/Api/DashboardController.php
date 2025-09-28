<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard metrics.
     *
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics,
                'message' => 'Dashboard metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve dashboard metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overview metrics only.
     *
     * @return JsonResponse
     */
    public function overview(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['overview'],
                'message' => 'Overview metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve overview metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial metrics only.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function financial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:month,year,custom',
            'start_date' => 'required_if:period,custom|date',
            'end_date' => 'required_if:period,custom|date|after_or_equal:start_date',
        ]);

        try {
            $metrics = $this->dashboardService->getMetrics();
            $financialMetrics = $metrics['financial'];

            return response()->json([
                'data' => $financialMetrics,
                'message' => 'Financial metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve financial metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project metrics only.
     *
     * @return JsonResponse
     */
    public function projects(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['projects'],
                'message' => 'Project metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve project metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get client metrics only.
     *
     * @return JsonResponse
     */
    public function clients(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['clients'],
                'message' => 'Client metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve client metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice metrics only.
     *
     * @return JsonResponse
     */
    public function invoices(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['invoices'],
                'message' => 'Invoice metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve invoice metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trends metrics only.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:month,quarter,year',
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['trends'],
                'message' => 'Trends metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve trends metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activity.
     *
     * @return JsonResponse
     */
    public function activity(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();

            return response()->json([
                'data' => $metrics['recent_activity'],
                'message' => 'Recent activity retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve recent activity: ' . $e->getMessage()
            ], 500);
        }
    }
}