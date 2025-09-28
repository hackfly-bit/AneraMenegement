<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\FinanceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        return [
            'overview' => $this->getOverviewMetrics(),
            'financial' => $this->getFinancialMetrics($startOfMonth, $endOfMonth),
            'projects' => $this->getProjectMetrics(),
            'clients' => $this->getClientMetrics(),
            'invoices' => $this->getInvoiceMetrics(),
            'trends' => $this->getTrendsMetrics($startOfYear, $now),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    /**
     * Get overview metrics.
     *
     * @return array
     */
    protected function getOverviewMetrics(): array
    {
        return [
            'total_clients' => Client::count(),
            'total_projects' => Project::count(),
            'total_invoices' => Invoice::count(),
            'total_payments' => Payment::count(),
            'active_projects' => Project::where('status', 'in_progress')->count(),
            'completed_projects' => Project::where('status', 'completed')->count(),
        ];
    }

    /**
     * Get financial metrics.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getFinancialMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $monthlyIncome = FinanceTransaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $monthlyExpenses = FinanceTransaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $totalIncome = FinanceTransaction::where('transaction_type', 'income')->sum('amount');
        $totalExpenses = FinanceTransaction::where('transaction_type', 'expense')->sum('amount');

        $monthlyPayments = Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');
        $totalOutstanding = Invoice::where('status', 'sent')->sum('total');

        return [
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'monthly_profit' => $monthlyIncome - $monthlyExpenses,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_profit' => $totalIncome - $totalExpenses,
            'monthly_payments' => $monthlyPayments,
            'total_outstanding' => $totalOutstanding,
            'profit_margin' => $monthlyIncome > 0 ? (($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100 : 0,
        ];
    }

    /**
     * Get project metrics.
     *
     * @return array
     */
    protected function getProjectMetrics(): array
    {
        $projectsByStatus = Project::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $avgProjectValue = Project::avg('budget') ?? 0;
        $totalProjectValue = Project::sum('budget') ?? 0;

        return [
            'by_status' => $projectsByStatus,
            'total_value' => $totalProjectValue,
            'average_value' => $avgProjectValue,
            'completion_rate' => $this->calculateProjectCompletionRate(),
        ];
    }

    /**
     * Get client metrics.
     *
     * @return array
     */
    protected function getClientMetrics(): array
    {
        $activeClients = Client::whereHas('projects', function ($query) {
            $query->whereIn('status', ['in_progress', 'completed']);
        })->count();

        $newClientsThisMonth = Client::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $topClients = Client::withCount('projects')
            ->withSum('projects', 'budget')
            ->orderBy('projects_sum_budget', 'desc')
            ->limit(5)
            ->get();

        return [
            'active_clients' => $activeClients,
            'new_clients_this_month' => $newClientsThisMonth,
            'total_clients' => Client::count(),
            'top_clients' => $topClients,
        ];
    }

    /**
     * Get invoice metrics.
     *
     * @return array
     */
    protected function getInvoiceMetrics(): array
    {
        $invoicesByStatus = Invoice::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $overdueInvoices = Invoice::where('due_date', '<', Carbon::now())
            ->where('status', 'sent')
            ->count();

        $totalInvoiceValue = Invoice::sum('total') ?? 0;
        $paidInvoiceValue = Invoice::where('status', 'paid')->sum('total') ?? 0;

        return [
            'by_status' => $invoicesByStatus,
            'overdue_count' => $overdueInvoices,
            'total_value' => $totalInvoiceValue,
            'paid_value' => $paidInvoiceValue,
            'collection_rate' => $totalInvoiceValue > 0 ? ($paidInvoiceValue / $totalInvoiceValue) * 100 : 0,
        ];
    }

    /**
     * Get trends metrics.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getTrendsMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $monthlyTrends = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();

            $income = FinanceTransaction::where('transaction_type', 'income')
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $expenses = FinanceTransaction::where('transaction_type', 'expense')
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $monthlyTrends[] = [
                'month' => $currentDate->format('Y-m'),
                'income' => $income,
                'expenses' => $expenses,
                'profit' => $income - $expenses,
            ];

            $currentDate->addMonth();
        }

        return [
            'monthly' => $monthlyTrends,
            'income_growth' => $this->calculateGrowthRate($monthlyTrends, 'income'),
            'expense_growth' => $this->calculateGrowthRate($monthlyTrends, 'expenses'),
        ];
    }

    /**
     * Get recent activity.
     *
     * @return array
     */
    protected function getRecentActivity(): array
    {
        $recentProjects = Project::with('client')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $recentInvoices = Invoice::with('client')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentPayments = Payment::with('invoice.client')
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get();

        return [
            'recent_projects' => $recentProjects,
            'recent_invoices' => $recentInvoices,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Calculate project completion rate.
     *
     * @return float
     */
    protected function calculateProjectCompletionRate(): float
    {
        $totalProjects = Project::count();
        $completedProjects = Project::where('status', 'completed')->count();

        return $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;
    }

    /**
     * Calculate growth rate.
     *
     * @param array $data
     * @param string $field
     * @return float
     */
    protected function calculateGrowthRate(array $data, string $field): float
    {
        if (count($data) < 2) {
            return 0;
        }

        $firstValue = $data[0][$field] ?? 0;
        $lastValue = $data[count($data) - 1][$field] ?? 0;

        if ($firstValue == 0) {
            return $lastValue > 0 ? 100 : 0;
        }

        return (($lastValue - $firstValue) / $firstValue) * 100;
    }
}