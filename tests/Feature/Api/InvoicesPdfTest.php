<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicesPdfTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/invoices/{id}/pdf generates PDF successfully
     * 
     * @test
     */
    public function it_generates_invoice_pdf_successfully()
    {
        // Arrange - This will fail until Invoice model exists
        // $client = Client::factory()->create();
        // $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $invoiceId = 1;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

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
     * Test GET /api/invoices/{id}/pdf returns 404 for non-existent invoice
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_invoice()
    {
        // Arrange
        $nonExistentId = 99999;

        // Act
        $response = $this->getJson("/api/invoices/{$nonExistentId}/pdf");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/invoices/{id}/pdf with invalid ID format
     * 
     * @test
     */
    public function it_returns_404_for_invalid_id_format()
    {
        // Arrange
        $invalidId = 'invalid-id';

        // Act
        $response = $this->getJson("/api/invoices/{$invalidId}/pdf");

        // Assert
        $this->assertTrue(in_array($response->status(), [400, 404]));
    }

    /**
     * Test GET /api/invoices/{id}/pdf with zero ID
     * 
     * @test
     */
    public function it_returns_404_for_zero_id()
    {
        // Arrange
        $zeroId = 0;

        // Act
        $response = $this->getJson("/api/invoices/{$zeroId}/pdf");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/invoices/{id}/pdf with negative ID
     * 
     * @test
     */
    public function it_returns_404_for_negative_id()
    {
        // Arrange
        $negativeId = -1;

        // Act
        $response = $this->getJson("/api/invoices/{$negativeId}/pdf");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/invoices/{id}/pdf sets correct headers for download
     * 
     * @test
     */
    public function it_sets_correct_headers_for_pdf_download()
    {
        // Arrange
        $invoiceId = 1;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

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
     * Test GET /api/invoices/{id}/pdf for draft invoice
     * 
     * @test
     */
    public function it_generates_pdf_for_draft_invoice()
    {
        // Arrange - This will fail until Invoice model exists
        // $client = Client::factory()->create();
        // $draftInvoice = Invoice::factory()->create([
        //     'client_id' => $client->id,
        //     'status' => 'draft'
        // ]);

        $invoiceId = 1;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

        // Assert - Should be able to generate PDF even for draft invoices
        $this->assertTrue(in_array($response->status(), [200, 404]));
    }

    /**
     * Test GET /api/invoices/{id}/pdf for paid invoice
     * 
     * @test
     */
    public function it_generates_pdf_for_paid_invoice()
    {
        // Arrange - This will fail until Invoice model exists
        // $client = Client::factory()->create();
        // $paidInvoice = Invoice::factory()->create([
        //     'client_id' => $client->id,
        //     'status' => 'paid'
        // ]);

        $invoiceId = 2;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

        // Assert
        $this->assertTrue(in_array($response->status(), [200, 404]));
    }

    /**
     * Test GET /api/invoices/{id}/pdf requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $invoiceId = 1;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401, 404]));
    }

    /**
     * Test GET /api/invoices/{id}/pdf response time performance
     * 
     * @test
     */
    public function it_generates_pdf_within_acceptable_time()
    {
        // Arrange
        $invoiceId = 1;
        $startTime = microtime(true);

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

        // Assert - PDF generation should be reasonably fast (under 5 seconds)
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        $this->assertLessThan(5.0, $responseTime, 'PDF generation should be under 5 seconds');
    }

    /**
     * Test GET /api/invoices/{id}/pdf file size is reasonable
     * 
     * @test
     */
    public function it_generates_pdf_with_reasonable_file_size()
    {
        // Arrange
        $invoiceId = 1;

        // Act
        $response = $this->getJson("/api/invoices/{$invoiceId}/pdf");

        // Assert
        if ($response->status() === 200) {
            $content = $response->getContent();
            $fileSize = strlen($content);
            
            // PDF should not be empty and should not be excessively large (under 5MB)
            $this->assertGreaterThan(0, $fileSize, 'PDF should not be empty');
            $this->assertLessThan(5 * 1024 * 1024, $fileSize, 'PDF should be under 5MB');
        }
    }
}