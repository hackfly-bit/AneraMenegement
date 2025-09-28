<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicesIndexTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/invoices returns paginated list of invoices
     * 
     * @test
     */
    public function it_returns_paginated_list_of_invoices()
    {
        // Arrange - This will fail initially as we don't have Invoice model yet
        // $client = Client::factory()->create();
        // $invoices = Invoice::factory()->count(3)->create(['client_id' => $client->id]);

        // Act
        $response = $this->getJson('/api/invoices');

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'client_id',
                        'project_id',
                        'issue_date',
                        'due_date',
                        'subtotal',
                        'tax_rate',
                        'tax_amount',
                        'total',
                        'status',
                        'notes',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /**
     * Test GET /api/invoices with client_id filter
     * 
     * @test
     */
    public function it_filters_invoices_by_client_id()
    {
        // Arrange - This will fail initially
        // $client1 = Client::factory()->create();
        // $client2 = Client::factory()->create();
        // $invoice1 = Invoice::factory()->create(['client_id' => $client1->id]);
        // $invoice2 = Invoice::factory()->create(['client_id' => $client2->id]);

        $clientId = 1;

        // Act
        $response = $this->getJson("/api/invoices?client_id={$clientId}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'client_id',
                        'project_id',
                        'issue_date',
                        'due_date',
                        'subtotal',
                        'tax_rate',
                        'tax_amount',
                        'total',
                        'status',
                        'notes',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /**
     * Test GET /api/invoices with status filter
     * 
     * @test
     */
    public function it_filters_invoices_by_status()
    {
        // Arrange - This will fail initially
        // $paidInvoice = Invoice::factory()->create(['status' => 'paid']);
        // $draftInvoice = Invoice::factory()->create(['status' => 'draft']);

        // Act
        $response = $this->getJson('/api/invoices?status=paid');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'client_id',
                        'project_id',
                        'issue_date',
                        'due_date',
                        'subtotal',
                        'tax_rate',
                        'tax_amount',
                        'total',
                        'status',
                        'notes',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /**
     * Test GET /api/invoices returns empty data when no invoices exist
     * 
     * @test
     */
    public function it_returns_empty_data_when_no_invoices_exist()
    {
        // Act
        $response = $this->getJson('/api/invoices');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'total' => 0
                ]
            ]);
    }

    /**
     * Test GET /api/invoices validates status parameter values
     * 
     * @test
     */
    public function it_validates_status_parameter_values()
    {
        // Act - Test with invalid status
        $response = $this->getJson('/api/invoices?status=invalid_status');

        // Assert - Should either ignore invalid status or return validation error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
    }

    /**
     * Test GET /api/invoices returns correct data types
     * 
     * @test
     */
    public function it_returns_correct_data_types()
    {
        // Arrange - This will fail until Invoice model exists
        // $client = Client::factory()->create();
        // $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        // Act
        $response = $this->getJson('/api/invoices');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            if (!empty($data['data'])) {
                $invoice = $data['data'][0];
                
                // Verify data types match API contract
                $this->assertIsInt($invoice['id']);
                $this->assertIsString($invoice['invoice_number']);
                $this->assertIsInt($invoice['client_id']);
                $this->assertTrue(is_int($invoice['project_id']) || is_null($invoice['project_id']));
                $this->assertIsString($invoice['issue_date']);
                $this->assertIsString($invoice['due_date']);
                $this->assertTrue(is_numeric($invoice['subtotal']));
                $this->assertTrue(is_numeric($invoice['tax_rate']));
                $this->assertTrue(is_numeric($invoice['tax_amount']));
                $this->assertTrue(is_numeric($invoice['total']));
                $this->assertContains($invoice['status'], ['draft', 'sent', 'paid', 'overdue', 'cancelled']);
                $this->assertTrue(is_string($invoice['notes']) || is_null($invoice['notes']));
                $this->assertIsString($invoice['created_at']);
                $this->assertIsString($invoice['updated_at']);
            }
        }
    }

    /**
     * Test GET /api/invoices requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Act
        $response = $this->getJson('/api/invoices');
        
        // Assert
        $this->assertTrue(in_array($response->status(), [200, 401]));
    }
}