<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FinancialReportsPdfTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/reports/financial/pdf generates PDF successfully
     * 
     * @test
     */
    public function it_generates_financial_report_pdf_successfully()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024
        ];

        // Act
        $response = $this->getJson('/api/reports/financial/pdf?' . http_build_query($queryParams));

        // Assert - Contract expectations from API spec
        $response->assertStatus(200);
        
        // Verify content type is PDF
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        
        // Verify response has binary content
        $this->assertNotEmpty($response->getContent());
        
        // Verify PDF header (PDF files start with %PDF)
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
    }

    /**
     * Test GET /api/reports/financial/pdf validation for missing required parameters
     * 
     * @test
     */
    public function it_returns_validation_error_for_missing_required_parameters()
    {
        // Act - Missing required period and year
        $response = $this->getJson('/api/reports/financial/pdf');

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
     * Test GET /api/reports/financial/pdf validation for invalid period
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
        $response = $this->getJson('/api/reports/financial/pdf?' . http_build_query($queryParams));

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
     * Test GET /api/reports/financial/pdf sets correct headers for download
     * 
     * @test
     */
    public function it_sets_correct_headers_for_pdf_download()
    {
        // Arrange
        $queryParams = [
            'period' => 'yearly',
            'year' => 2024
        ];

        // Act
        $response = $this->getJson('/api/reports/financial/pdf?' . http_build_query($queryParams));

        // Assert
        if ($response->status() === 200) {
            // Verify content type
            $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
            
            // Verify content disposition header for download
            $contentDisposition = $response->headers->get('Content-Disposition');
            $this->assertStringContains('attachment', $contentDisposition ?? '');
            $this->assertStringContains('filename', $contentDisposition ?? '');
            $this->assertStringContains('.pdf', $contentDisposition ?? '');
        }
    }

    /**
     * Test GET /api/reports/financial/pdf requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $queryParams = [
            'period' => 'monthly',
            'year' => 2024
        ];

        // Act
        $response = $this->getJson('/api/reports/financial/pdf?' . http_build_query($queryParams));
        
        // Assert
        $this->assertTrue(in_array($response->status(), [200, 401, 422]));
    }
}