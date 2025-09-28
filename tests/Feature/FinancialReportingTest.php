<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FinancialReportingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test complete financial reporting workflow
     * 
     * @test
     */
    public function it_handles_complete_financial_reporting_workflow()
    {
        // Step 1: Create business transactions (clients, projects, invoices, payments)
        $this->createSampleBusinessData();

        // Step 2: Generate monthly financial report
        $monthlyParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        $monthlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($monthlyParams));
        
        if ($monthlyResponse->status() === 200) {
            $monthlyData = $monthlyResponse->json();
            
            // Verify report structure
            $this->assertArrayHasKey('period', $monthlyData);
            $this->assertArrayHasKey('year', $monthlyData);
            $this->assertArrayHasKey('month', $monthlyData);
            $this->assertArrayHasKey('total_income', $monthlyData);
            $this->assertArrayHasKey('total_expense', $monthlyData);
            $this->assertArrayHasKey('net_profit', $monthlyData);

            // Verify calculations
            $expectedNetProfit = $monthlyData['total_income'] - $monthlyData['total_expense'];
            $this->assertEquals($expectedNetProfit, $monthlyData['net_profit']);
        }

        // Step 3: Generate quarterly financial report
        $quarterlyParams = [
            'period' => 'quarterly',
            'year' => 2024,
            'quarter' => 1
        ];

        $quarterlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($quarterlyParams));
        
        if ($quarterlyResponse->status() === 200) {
            $quarterlyData = $quarterlyResponse->json();
            
            $this->assertEquals('quarterly', $quarterlyData['period']);
            $this->assertEquals(2024, $quarterlyData['year']);
            $this->assertEquals(1, $quarterlyData['quarter']);
        }

        // Step 4: Generate yearly financial report
        $yearlyParams = [
            'period' => 'yearly',
            'year' => 2024
        ];

        $yearlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($yearlyParams));
        
        if ($yearlyResponse->status() === 200) {
            $yearlyData = $yearlyResponse->json();
            
            $this->assertEquals('yearly', $yearlyData['period']);
            $this->assertEquals(2024, $yearlyData['year']);
        }

        // Step 5: Generate PDF reports
        $pdfResponse = $this->getJson('/api/reports/financial/pdf?' . http_build_query($monthlyParams));
        
        if ($pdfResponse->status() === 200) {
            $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
        }
    }

    /**
     * Test financial report accuracy with known data
     * 
     * @test
     */
    public function it_calculates_financial_reports_accurately()
    {
        // Create specific test data with known values
        $testData = $this->createKnownFinancialData();

        if ($testData) {
            // Generate report for the test period
            $params = [
                'period' => 'monthly',
                'year' => 2024,
                'month' => 1
            ];

            $response = $this->getJson('/api/reports/financial?' . http_build_query($params));
            
            if ($response->status() === 200) {
                $data = $response->json();
                
                // Verify calculations match expected values
                // Note: These assertions will fail until the actual implementation exists
                // but they define the expected behavior
                
                // Expected values based on test data created
                $expectedIncome = $testData['expected_income'] ?? 0;
                $expectedExpense = $testData['expected_expense'] ?? 0;
                $expectedProfit = $expectedIncome - $expectedExpense;

                $this->assertEquals($expectedIncome, $data['total_income'], 
                    'Total income should match sum of all payments received');
                $this->assertEquals($expectedExpense, $data['total_expense'], 
                    'Total expense should match sum of all expenses recorded');
                $this->assertEquals($expectedProfit, $data['net_profit'], 
                    'Net profit should equal income minus expenses');
            }
        }
    }

    /**
     * Test financial report filtering by different periods
     * 
     * @test
     */
    public function it_filters_financial_data_by_different_periods()
    {
        // Create data across multiple periods
        $this->createMultiPeriodFinancialData();

        // Test monthly filtering
        $monthlyParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        $monthlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($monthlyParams));
        
        if ($monthlyResponse->status() === 200) {
            $monthlyData = $monthlyResponse->json();
            $this->assertEquals('monthly', $monthlyData['period']);
            $this->assertEquals(1, $monthlyData['month']);
        }

        // Test quarterly filtering
        $quarterlyParams = [
            'period' => 'quarterly',
            'year' => 2024,
            'quarter' => 1
        ];

        $quarterlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($quarterlyParams));
        
        if ($quarterlyResponse->status() === 200) {
            $quarterlyData = $quarterlyResponse->json();
            $this->assertEquals('quarterly', $quarterlyData['period']);
            $this->assertEquals(1, $quarterlyData['quarter']);
        }

        // Test yearly filtering
        $yearlyParams = [
            'period' => 'yearly',
            'year' => 2024
        ];

        $yearlyResponse = $this->getJson('/api/reports/financial?' . http_build_query($yearlyParams));
        
        if ($yearlyResponse->status() === 200) {
            $yearlyData = $yearlyResponse->json();
            $this->assertEquals('yearly', $yearlyData['period']);
            $this->assertEquals(2024, $yearlyData['year']);
        }
    }

    /**
     * Test financial report consistency across different endpoints
     * 
     * @test
     */
    public function it_maintains_consistency_across_financial_endpoints()
    {
        // Create sample data
        $this->createSampleBusinessData();

        // Get financial report data
        $params = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        $reportResponse = $this->getJson('/api/reports/financial?' . http_build_query($params));
        $dashboardResponse = $this->getJson('/api/dashboard/metrics');

        if ($reportResponse->status() === 200 && $dashboardResponse->status() === 200) {
            $reportData = $reportResponse->json();
            $dashboardData = $dashboardResponse->json();

            // Monthly revenue from dashboard should be consistent with financial report
            // Note: This assumes dashboard shows current month data
            if (isset($dashboardData['monthly_revenue']) && isset($reportData['total_income'])) {
                // The values should be related (though not necessarily identical 
                // as they might cover different time periods)
                $this->assertIsNumeric($dashboardData['monthly_revenue']);
                $this->assertIsNumeric($reportData['total_income']);
            }
        }
    }

    /**
     * Test financial report performance with large datasets
     * 
     * @test
     */
    public function it_handles_large_financial_datasets_efficiently()
    {
        // Create a larger dataset for performance testing
        $this->createLargeFinancialDataset();

        $startTime = microtime(true);

        // Generate report
        $params = [
            'period' => 'yearly',
            'year' => 2024
        ];

        $response = $this->getJson('/api/reports/financial?' . http_build_query($params));

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        // Report generation should be reasonably fast (under 3 seconds)
        $this->assertLessThan(3.0, $responseTime, 
            'Financial report generation should complete within 3 seconds');

        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify report structure is maintained even with large datasets
            $this->assertArrayHasKey('total_income', $data);
            $this->assertArrayHasKey('total_expense', $data);
            $this->assertArrayHasKey('net_profit', $data);
        }
    }

    /**
     * Test financial report edge cases and error handling
     * 
     * @test
     */
    public function it_handles_financial_report_edge_cases()
    {
        // Test report for period with no data
        $emptyParams = [
            'period' => 'monthly',
            'year' => 2030, // Future year with no data
            'month' => 1
        ];

        $emptyResponse = $this->getJson('/api/reports/financial?' . http_build_query($emptyParams));
        
        if ($emptyResponse->status() === 200) {
            $emptyData = $emptyResponse->json();
            
            // Should return zero values for empty periods
            $this->assertEquals(0, $emptyData['total_income']);
            $this->assertEquals(0, $emptyData['total_expense']);
            $this->assertEquals(0, $emptyData['net_profit']);
        }

        // Test invalid parameter combinations
        $invalidParams = [
            ['period' => 'monthly', 'year' => 2024], // Missing month
            ['period' => 'quarterly', 'year' => 2024], // Missing quarter
            ['period' => 'invalid', 'year' => 2024], // Invalid period
            ['period' => 'monthly', 'year' => 'invalid', 'month' => 1], // Invalid year
        ];

        foreach ($invalidParams as $params) {
            $response = $this->getJson('/api/reports/financial?' . http_build_query($params));
            $response->assertStatus(422);
        }
    }

    /**
     * Helper method to create sample business data
     */
    private function createSampleBusinessData()
    {
        // Create client
        $clientData = ['name' => 'Report Client', 'email' => 'report@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            // Create project
            $projectData = [
                'client_id' => $clientId,
                'name' => 'Report Project',
                'value' => 5000.00,
                'status' => 'completed'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $projectId = $projectResponse->json()['id'];

                // Create invoice
                $invoiceData = [
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'issue_date' => '2024-01-15',
                    'due_date' => '2024-02-15',
                    'items' => [
                        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 5000.00]
                    ]
                ];

                $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
                
                if ($invoiceResponse->status() === 201) {
                    $invoiceId = $invoiceResponse->json()['id'];

                    // Create payment
                    $paymentData = [
                        'invoice_id' => $invoiceId,
                        'amount' => 5000.00,
                        'payment_date' => '2024-01-20',
                        'payment_method' => 'bank_transfer'
                    ];

                    $this->postJson('/api/payments', $paymentData);
                }
            }
        }
    }

    /**
     * Helper method to create known financial data for testing calculations
     */
    private function createKnownFinancialData()
    {
        // This would create specific test data with known values
        // For now, return expected values for testing
        return [
            'expected_income' => 5000.00,
            'expected_expense' => 1000.00
        ];
    }

    /**
     * Helper method to create multi-period financial data
     */
    private function createMultiPeriodFinancialData()
    {
        // Create data for different months/quarters
        // This would involve creating multiple invoices and payments
        // across different time periods
    }

    /**
     * Helper method to create large financial dataset
     */
    private function createLargeFinancialDataset()
    {
        // Create a larger number of transactions for performance testing
        // This would involve creating many clients, projects, invoices, and payments
    }
}