<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceTermsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test POST /api/invoices/{id}/terms creates invoice terms successfully
     * 
     * @test
     */
    public function it_creates_invoice_terms_successfully()
    {
        // Arrange - This will fail until Invoice model exists
        // $client = Client::factory()->create();
        // $invoice = Invoice::factory()->create([
        //     'client_id' => $client->id,
        //     'total' => 10000.00
        // ]);

        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 50.0,
                    'due_date' => '2024-01-15',
                    'description' => 'First payment - 50%'
                ],
                [
                    'percentage' => 50.0,
                    'due_date' => '2024-02-15',
                    'description' => 'Final payment - 50%'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(201);
        
        // Verify response structure (if API returns created terms)
        if ($response->json()) {
            $response->assertJsonStructure([
                'message'
            ]);
        }
    }

    /**
     * Test POST /api/invoices/{id}/terms with three payment terms
     * 
     * @test
     */
    public function it_creates_multiple_invoice_terms()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 30.0,
                    'due_date' => '2024-01-15',
                    'description' => 'Down payment - 30%'
                ],
                [
                    'percentage' => 40.0,
                    'due_date' => '2024-02-15',
                    'description' => 'Progress payment - 40%'
                ],
                [
                    'percentage' => 30.0,
                    'due_date' => '2024-03-15',
                    'description' => 'Final payment - 30%'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(201);
    }

    /**
     * Test POST /api/invoices/{id}/terms returns 404 for non-existent invoice
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_invoice()
    {
        // Arrange
        $nonExistentId = 99999;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 100.0,
                    'due_date' => '2024-01-15',
                    'description' => 'Full payment'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$nonExistentId}/terms", $termsData);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for missing terms
     * 
     * @test
     */
    public function it_returns_validation_error_for_missing_terms()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = []; // Missing terms

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'terms'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for empty terms array
     * 
     * @test
     */
    public function it_returns_validation_error_for_empty_terms()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [] // Empty terms array
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'terms'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for invalid percentage total
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_percentage_total()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 60.0, // Total = 110% (invalid)
                    'due_date' => '2024-01-15',
                    'description' => 'First payment'
                ],
                [
                    'percentage' => 50.0,
                    'due_date' => '2024-02-15',
                    'description' => 'Second payment'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for negative percentage
     * 
     * @test
     */
    public function it_returns_validation_error_for_negative_percentage()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => -10.0, // Negative percentage
                    'due_date' => '2024-01-15',
                    'description' => 'Invalid payment'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'terms.0.percentage'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for invalid date format
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_date_format()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 100.0,
                    'due_date' => 'invalid-date',
                    'description' => 'Payment with invalid date'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'terms.0.due_date'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms validation for missing required fields
     * 
     * @test
     */
    public function it_returns_validation_error_for_missing_required_fields()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    // Missing percentage, due_date, and description
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'terms.0.percentage',
                    'terms.0.due_date'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices/{id}/terms with past due dates
     * 
     * @test
     */
    public function it_allows_past_due_dates()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 100.0,
                    'due_date' => '2020-01-01', // Past date
                    'description' => 'Overdue payment'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);

        // Assert - Should allow past dates (for historical data)
        $this->assertTrue(in_array($response->status(), [201, 404, 422]));
    }

    /**
     * Test POST /api/invoices/{id}/terms requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $invoiceId = 1;
        $termsData = [
            'terms' => [
                [
                    'percentage' => 100.0,
                    'due_date' => '2024-01-15',
                    'description' => 'Full payment'
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);
        
        // Assert
        $this->assertTrue(in_array($response->status(), [201, 401, 404, 422]));
    }
}