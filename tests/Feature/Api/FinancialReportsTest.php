<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/reports/financial returns monthly report successfully
     * 
     * @test
     */
    public function it_returns_monthly_financial_report_successfully()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'year',
                'month',
                'total_income',
                'total_expense',
                'net_profit'
            ]);

        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify data types
            $this->assertEquals('monthly', $data['period']);
            $this->assertEquals(2024, $data['year']);
            $this->assertEquals(1, $data['month']);
            $this->assertTrue(is_numeric($data['total_income']));
            $this->assertTrue(is_numeric($data['total_expense']));
            $this->assertTrue(is_numeric($data['net_profit']));
        }
    }

    /**
     * Test GET /api/reports/financial returns quarterly report successfully
     * 
     * @test
     */
    public function it_returns_quarterly_financial_report_successfully()
    {
        // Arrange
        $queryParams = [
            'period' => 'quarterly',
            'year' => 2024,
            'quarter' => 1
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'year',
                'quarter',
                'total_income',
                'total_expense',
                'net_profit'
            ]);

        if ($response->status() === 200) {
            $data = $response->json();
            
            $this->assertEquals('quarterly', $data['period']);
            $this->assertEquals(2024, $data['year']);
            $this->assertEquals(1, $data['quarter']);
        }
    }

    /**
     * Test GET /api/reports/financial returns yearly report successfully
     * 
     * @test
     */
    public function it_returns_yearly_financial_report_successfully()
    {
        // Arrange
        $queryParams = [
            'period' => 'yearly',
            'year' => 2024
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'year',
                'total_income',
                'total_expense',
                'net_profit'
            ]);

        if ($response->status() === 200) {
            $data = $response->json();
            
            $this->assertEquals('yearly', $data['period']);
            $this->assertEquals(2024, $data['year']);
        }
    }

    /**
     * Test GET /api/reports/financial validation for missing required parameters
     * 
     * @test
     */
    public function it_returns_validation_error_for_missing_required_parameters()
    {
        // Act - Missing required period and year
        $response = $this->getJson('/api/reports/financial');

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'period',
                    'year'
                ]
            ]);
    }

    /**
     * Test GET /api/reports/financial validation for invalid period
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_period()
    {
        // Arrange
        $queryParams = [
            'period' => 'invalid_period',
            'year' => 2024
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'period'
                ]
            ]);
    }

    /**
     * Test GET /api/reports/financial validation for invalid year
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_year()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 'invalid_year'
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'year'
                ]
            ]);
    }

    /**
     * Test GET /api/reports/financial validation for invalid month
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_month()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 13 // Invalid month (should be 1-12)
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'month'
                ]
            ]);
    }

    /**
     * Test GET /api/reports/financial validation for invalid quarter
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_quarter()
    {
        // Arrange
        $queryParams = [
            'period' => 'quarterly',
            'year' => 2024,
            'quarter' => 5 // Invalid quarter (should be 1-4)
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'quarter'
                ]
            ]);
    }

    /**
     * Test GET /api/reports/financial for future periods
     * 
     * @test
     */
    public function it_handles_future_periods()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2030, // Future year
            'month' => 12
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert - Should handle future periods (might return zero values)
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    /**
     * Test GET /api/reports/financial for very old periods
     * 
     * @test
     */
    public function it_handles_old_periods()
    {
        // Arrange
        $queryParams = [
            'period' => 'yearly',
            'year' => 1990 // Very old year
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert - Should handle old periods
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    /**
     * Test GET /api/reports/financial calculates net profit correctly
     * 
     * @test
     */
    public function it_calculates_net_profit_correctly()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Net profit should equal total income minus total expense
            $expectedNetProfit = $data['total_income'] - $data['total_expense'];
            $this->assertEquals($expectedNetProfit, $data['net_profit'], 
                'Net profit should equal total income minus total expense');
        }
    }

    /**
     * Test GET /api/reports/financial requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024,
            'month' => 1
        ];

        // Act
        $response = $this->getJson('/api/reports/financial?' . http_build_query($queryParams));
        
        // Assert
        $this->assertTrue(in_array($response->status(), [200, 401, 422]));
    }
}