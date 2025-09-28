<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialReportService
{
    /**
     * Generate monthly financial report.
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function generateMonthlyReport(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return $this->generatePeriodReport($startDate, $endDate, 'monthly');
    }

    /**
     * Generate quarterly financial report.
     *
     * @param int $year
     * @param int $quarter
     * @return array
     */
    public function generateQuarterlyReport(int $year, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();

        return $this->generatePeriodReport($startDate, $endDate, 'quarterly');
    }

    /**
     * Generate yearly financial report.
     *
     * @param int $year
     * @return array
     */
    public function generateYearlyReport(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        return $this->generatePeriodReport($startDate, $endDate, 'yearly');
    }

    /**
     * Generate custom period financial report.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string $reportType
     * @return array
     */
    protected function generatePeriodReport(Carbon $startDate, Carbon $endDate, string $reportType): array
    {
        try {
            $report = [
                'report_type' => $reportType,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'duration' => $startDate->diffInDays($endDate) + 1,
                ],
                'summary' => $this->getFinancialSummary($startDate, $endDate),
                'income_analysis' => $this->getIncomeAnalysis($startDate, $endDate),
                'expense_analysis' => $this->getExpenseAnalysis($startDate, $endDate),
                'cash_flow' => $this->getCashFlowAnalysis($startDate, $endDate),
                'client_analysis' => $this->getClientAnalysis($startDate, $endDate),
                'project_analysis' => $this->getProjectAnalysis($startDate, $endDate),
                'invoice_analysis' => $this->getInvoiceAnalysis($startDate, $endDate),
                'payment_analysis' => $this->getPaymentAnalysis($startDate, $endDate),
                'account_balances' => $this->getAccountBalances($endDate),
                'trends' => $this->getTrendsAnalysis($startDate, $endDate),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ];

            Log::info("Financial {$reportType} report generated", [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

            return $report;
        } catch (\Exception $e) {
            Log::error("Failed to generate financial {$reportType} report", [
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);
            throw $e;
        }
    }

    /**
     * Get financial summary for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getFinancialSummary(Carbon $startDate, Carbon $endDate): array
    {
        $income = FinanceTransaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $expenses = FinanceTransaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $netIncome = $income - $expenses;
        $profitMargin = $income > 0 ? ($netIncome / $income) * 100 : 0;

        return [
            'total_income' => $income,
            'total_expenses' => $expenses,
            'net_income' => $netIncome,
            'profit_margin' => round($profitMargin, 2),
            'income_vs_expenses' => [
                'income' => $income,
                'expenses' => $expenses,
                'difference' => $netIncome,
            ],
        ];
    }

    /**
     * Get income analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getIncomeAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $incomeBySource = FinanceTransaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('description', DB::raw('SUM(amount) as total'))
            ->groupBy('description')
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->description,
                    'amount' => $item->total,
                ];
            });

        $incomeByMonth = FinanceTransaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month, 1)->format('Y-m'),
                    'amount' => $item->total,
                ];
            });

        $topIncomeTransactions = FinanceTransaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_income' => $incomeBySource->sum('amount'),
            'income_by_source' => $incomeBySource,
            'income_by_month' => $incomeByMonth,
            'top_transactions' => $topIncomeTransactions,
            'average_transaction' => $incomeBySource->count() > 0 ? $incomeBySource->sum('amount') / $incomeBySource->count() : 0,
        ];
    }

    /**
     * Get expense analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getExpenseAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $expensesByCategory = FinanceTransaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('description', DB::raw('SUM(amount) as total'))
            ->groupBy('description')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->description,
                    'amount' => $item->total,
                ];
            });

        $expensesByMonth = FinanceTransaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month, 1)->format('Y-m'),
                    'amount' => $item->total,
                ];
            });

        $topExpenseTransactions = FinanceTransaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_expenses' => $expensesByCategory->sum('amount'),
            'expenses_by_category' => $expensesByCategory,
            'expenses_by_month' => $expensesByMonth,
            'top_transactions' => $topExpenseTransactions,
            'average_transaction' => $expensesByCategory->count() > 0 ? $expensesByCategory->sum('amount') / $expensesByCategory->count() : 0,
        ];
    }

    /**
     * Get cash flow analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getCashFlowAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $dailyCashFlow = FinanceTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(CASE WHEN transaction_type = "income" THEN amount ELSE 0 END) as income'),
                DB::raw('SUM(CASE WHEN transaction_type = "expense" THEN amount ELSE 0 END) as expenses'),
                DB::raw('SUM(CASE WHEN transaction_type = "income" THEN amount ELSE -amount END) as net_cash_flow')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $openingBalance = $this->getAccountBalanceBefore($startDate);
        $closingBalance = $this->getAccountBalanceAt($endDate);

        $netCashFlow = $dailyCashFlow->sum('net_cash_flow');
        $averageDailyFlow = $dailyCashFlow->count() > 0 ? $netCashFlow / $dailyCashFlow->count() : 0;

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'net_cash_flow' => $netCashFlow,
            'average_daily_flow' => round($averageDailyFlow, 2),
            'daily_cash_flow' => $dailyCashFlow,
            'cash_flow_summary' => [
                'total_income' => $dailyCashFlow->sum('income'),
                'total_expenses' => $dailyCashFlow->sum('expenses'),
                'net_flow' => $netCashFlow,
            ],
        ];
    }

    /**
     * Get client analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getClientAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $topClientsByRevenue = Client::with(['invoices' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'paid');
            }])
            ->get()
            ->map(function ($client) {
                return [
                    'client' => $client,
                    'total_revenue' => $client->invoices->sum('total'),
                    'invoice_count' => $client->invoices->count(),
                    'average_invoice' => $client->invoices->count() > 0 ? $client->invoices->avg('total') : 0,
                ];
            })
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        $clientGrowth = Client::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $activeClients = Client::whereHas('invoices', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->count();

        return [
            'total_clients' => Client::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_clients' => $activeClients,
            'top_clients_by_revenue' => $topClientsByRevenue,
            'client_growth' => $clientGrowth,
            'client_retention' => $this->calculateClientRetention($startDate, $endDate),
        ];
    }

    /**
     * Get project analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getProjectAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $projectsByStatus = Project::whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            });

        $projectsByValue = Project::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('CASE 
                    WHEN value < 1000 THEN "Under 1K"
                    WHEN value < 5000 THEN "1K - 5K"
                    WHEN value < 10000 THEN "5K - 10K"
                    WHEN value < 50000 THEN "10K - 50K"
                    ELSE "Over 50K"
                END as range'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(value) as total_value')
            )
            ->groupBy('range')
            ->get();

        $averageProjectValue = Project::whereBetween('created_at', [$startDate, $endDate])->avg('value');

        return [
            'total_projects' => Project::whereBetween('created_at', [$startDate, $endDate])->count(),
            'projects_by_status' => $projectsByStatus,
            'projects_by_value_range' => $projectsByValue,
            'average_project_value' => round($averageProjectValue, 2),
            'project_completion_rate' => $this->calculateProjectCompletionRate($startDate, $endDate),
        ];
    }

    /**
     * Get invoice analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getInvoiceAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $invoicesByStatus = Invoice::whereBetween('issue_date', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            });

        $invoicesByMonth = Invoice::whereBetween('issue_date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(issue_date) as month'),
                DB::raw('YEAR(issue_date) as year'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month, 1)->format('Y-m'),
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            });

        $averageInvoiceValue = Invoice::whereBetween('issue_date', [$startDate, $endDate])->avg('total');

        return [
            'total_invoices' => Invoice::whereBetween('issue_date', [$startDate, $endDate])->count(),
            'total_invoice_value' => Invoice::whereBetween('issue_date', [$startDate, $endDate])->sum('total'),
            'average_invoice_value' => round($averageInvoiceValue, 2),
            'invoices_by_status' => $invoicesByStatus,
            'invoices_by_month' => $invoicesByMonth,
            'overdue_rate' => $this->calculateOverdueRate($startDate, $endDate),
        ];
    }

    /**
     * Get payment analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getPaymentAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $paymentsByMethod = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method,
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            });

        $paymentsByMonth = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(payment_date) as month'),
                DB::raw('YEAR(payment_date) as year'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month, 1)->format('Y-m'),
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            });

        $averagePayment = Payment::whereBetween('payment_date', [$startDate, $endDate])->avg('amount');

        return [
            'total_payments' => Payment::whereBetween('payment_date', [$startDate, $endDate])->count(),
            'total_payment_amount' => Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount'),
            'average_payment_amount' => round($averagePayment, 2),
            'payments_by_method' => $paymentsByMethod,
            'payments_by_month' => $paymentsByMonth,
            'collection_rate' => $this->calculateCollectionRate($startDate, $endDate),
        ];
    }

    /**
     * Get account balances.
     *
     * @param Carbon $date
     * @return array
     */
    protected function getAccountBalances(Carbon $date): array
    {
        $accounts = Account::with(['financeTransactions' => function ($query) use ($date) {
                $query->where('transaction_date', '<=', $date);
            }])
            ->get()
            ->map(function ($account) {
                return [
                    'account' => $account,
                    'balance' => $account->balance,
                    'total_income' => $account->financeTransactions->where('transaction_type', 'income')->sum('amount'),
                    'total_expenses' => $account->financeTransactions->where('transaction_type', 'expense')->sum('amount'),
                ];
            });

        return [
            'accounts' => $accounts,
            'total_assets' => $accounts->sum('balance'),
            'total_income_accounts' => $accounts->where('account.type', 'income')->sum('balance'),
            'total_expense_accounts' => $accounts->where('account.type', 'expense')->sum('balance'),
        ];
    }

    /**
     * Get trends analysis for a period.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function getTrendsAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $previousPeriodStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1);
        $previousPeriodEnd = $startDate->copy()->subDay();

        $currentPeriod = $this->getFinancialSummary($startDate, $endDate);
        $previousPeriod = $this->getFinancialSummary($previousPeriodStart, $previousPeriodEnd);

        $incomeGrowth = $previousPeriod['total_income'] > 0 
            ? (($currentPeriod['total_income'] - $previousPeriod['total_income']) / $previousPeriod['total_income']) * 100 
            : 0;

        $expenseGrowth = $previousPeriod['total_expenses'] > 0 
            ? (($currentPeriod['total_expenses'] - $previousPeriod['total_expenses']) / $previousPeriod['total_expenses']) * 100 
            : 0;

        $netIncomeGrowth = $previousPeriod['net_income'] != 0 
            ? (($currentPeriod['net_income'] - $previousPeriod['net_income']) / abs($previousPeriod['net_income'])) * 100 
            : 0;

        return [
            'income_growth' => round($incomeGrowth, 2),
            'expense_growth' => round($expenseGrowth, 2),
            'net_income_growth' => round($netIncomeGrowth, 2),
            'previous_period' => $previousPeriod,
            'current_period' => $currentPeriod,
        ];
    }

    /**
     * Calculate client retention rate.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function calculateClientRetention(Carbon $startDate, Carbon $endDate): float
    {
        $previousPeriodStart = $startDate->copy()->subYear();
        $previousPeriodEnd = $startDate->copy()->subDay();

        $previousClients = Client::whereHas('invoices', function ($query) use ($previousPeriodStart, $previousPeriodEnd) {
            $query->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]);
        })->pluck('id');

        $currentClients = Client::whereHas('invoices', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->pluck('id');

        $retainedClients = $previousClients->intersect($currentClients);

        return $previousClients->count() > 0 
            ? ($retainedClients->count() / $previousClients->count()) * 100 
            : 0;
    }

    /**
     * Calculate project completion rate.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function calculateProjectCompletionRate(Carbon $startDate, Carbon $endDate): float
    {
        $totalProjects = Project::whereBetween('created_at', [$startDate, $endDate])->count();
        $completedProjects = Project::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();

        return $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;
    }

    /**
     * Calculate overdue rate.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function calculateOverdueRate(Carbon $startDate, Carbon $endDate): float
    {
        $totalInvoices = Invoice::whereBetween('issue_date', [$startDate, $endDate])->count();
        $overdueInvoices = Invoice::whereBetween('issue_date', [$startDate, $endDate])
            ->where('status', 'overdue')
            ->count();

        return $totalInvoices > 0 ? ($overdueInvoices / $totalInvoices) * 100 : 0;
    }

    /**
     * Calculate collection rate.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    protected function calculateCollectionRate(Carbon $startDate, Carbon $endDate): float
    {
        $totalInvoiceValue = Invoice::whereBetween('issue_date', [$startDate, $endDate])->sum('total');
        $collectedAmount = Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');

        return $totalInvoiceValue > 0 ? ($collectedAmount / $totalInvoiceValue) * 100 : 0;
    }

    /**
     * Get account balance before a specific date.
     *
     * @param Carbon $date
     * @return float
     */
    protected function getAccountBalanceBefore(Carbon $date): float
    {
        return FinanceTransaction::where('transaction_date', '<', $date)
            ->select(DB::raw('SUM(CASE WHEN transaction_type = "income" THEN amount ELSE -amount END) as balance'))
            ->value('balance') ?? 0;
    }

    /**
     * Get account balance at a specific date.
     *
     * @param Carbon $date
     * @return float
     */
    protected function getAccountBalanceAt(Carbon $date): float
    {
        return FinanceTransaction::where('transaction_date', '<=', $date)
            ->select(DB::raw('SUM(CASE WHEN transaction_type = "income" THEN amount ELSE -amount END) as balance'))
            ->value('balance') ?? 0;
    }

    /**
     * Generate custom period financial report.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function generateCustomPeriodReport(Carbon $startDate, Carbon $endDate): array
    {
        return $this->generatePeriodReport($startDate, $endDate, 'custom');
    }

    /**
     * Generate PDF report.
     *
     * @param array $reportData
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdfReport(array $reportData)
    {
        return Pdf::loadView('reports.financial-pdf', ['report' => $reportData]);
    }
}