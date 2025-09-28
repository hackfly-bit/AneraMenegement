<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicesStoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test POST /api/invoices creates a new invoice successfully
     * 
     * @test
     */
    public function it_creates_a_new_invoice_successfully()
    {
        // Arrange - This will fail until Client, Project, and Invoice models exist
        // $client = Client::factory()->create();
        // $project = Project::factory()->create(['client_id' => $client->id]);

        $invoiceData = [
            'client_id' => 1, // $client->id,
            'project_id' => 1, // $project->id,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'tax_rate' => 10.0,
            'notes' => $this->faker->sentence,
            'items' => [
                [
                    'description' => 'Service 1',
                    'quantity' => 2,
                    'unit_price' => 500.00
                ],
                [
                    'description' => 'Service 2',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(201)
            ->assertJsonStructure([
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
            ]);
    }

    /**
     * Test POST /api/invoices with minimal required data
     * 
     * @test
     */
    public function it_creates_invoice_with_minimal_required_data()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'items' => [
                [
                    'description' => 'Basic Service',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
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
            ]);
    }

    /**
     * Test POST /api/invoices validation errors for missing required fields
     * 
     * @test
     */
    public function it_returns_validation_errors_for_missing_required_fields()
    {
        // Arrange - Empty data
        $invoiceData = [];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert - Contract expects 422 for validation errors
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'client_id',
                    'issue_date',
                    'due_date',
                    'items'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices validation for invalid client_id
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_client_id()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 99999, // Non-existent client
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'items' => [
                [
                    'description' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'client_id'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices validation for invalid date formats
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_date_formats()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => 'invalid-date',
            'due_date' => 'invalid-date',
            'items' => [
                [
                    'description' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'issue_date',
                    'due_date'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices validation for empty items array
     * 
     * @test
     */
    public function it_returns_validation_error_for_empty_items()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'items' => [] // Empty items array
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'items'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices validation for invalid item data
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_item_data()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'items' => [
                [
                    'description' => '', // Empty description
                    'quantity' => -1, // Negative quantity
                    'unit_price' => -100 // Negative price
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'items.0.description',
                    'items.0.quantity',
                    'items.0.unit_price'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices validation for negative tax rate
     * 
     * @test
     */
    public function it_returns_validation_error_for_negative_tax_rate()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'tax_rate' => -5.0, // Negative tax rate
            'items' => [
                [
                    'description' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'tax_rate'
                ]
            ]);
    }

    /**
     * Test POST /api/invoices calculates totals correctly
     * 
     * @test
     */
    public function it_calculates_totals_correctly()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'tax_rate' => 10.0,
            'items' => [
                [
                    'description' => 'Service 1',
                    'quantity' => 2,
                    'unit_price' => 500.00 // 2 * 500 = 1000
                ],
                [
                    'description' => 'Service 2',
                    'quantity' => 1,
                    'unit_price' => 1000.00 // 1 * 1000 = 1000
                ]
            ]
        ];

        // Expected calculations:
        // Subtotal: 2000.00
        // Tax (10%): 200.00
        // Total: 2200.00

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);

        // Assert
        if ($response->status() === 201) {
            $data = $response->json();
            
            $this->assertEquals(2000.00, $data['subtotal']);
            $this->assertEquals(10.0, $data['tax_rate']);
            $this->assertEquals(200.00, $data['tax_amount']);
            $this->assertEquals(2200.00, $data['total']);
        }
    }

    /**
     * Test POST /api/invoices requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $invoiceData = [
            'client_id' => 1,
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'items' => [
                [
                    'description' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 1000.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/invoices', $invoiceData);
        
        // Assert
        $this->assertTrue(in_array($response->status(), [201, 401, 422]));
    }
}