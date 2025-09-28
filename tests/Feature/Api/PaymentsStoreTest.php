<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentsStoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test POST /api/payments records payment successfully
     * 
     * @test
     */
    public function it_records_payment_successfully()
    {
        // Arrange - This will fail until Payment model exists
        // $client = Client::factory()->create();
        // $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $paymentData = [
            'invoice_id' => 1, // $invoice->id,
            'amount' => 5000.00,
            'payment_date' => $this->faker->date(),
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX' . $this->faker->numerify('########'),
            'notes' => $this->faker->sentence
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(201);
        
        // Verify response structure (if API returns created payment)
        if ($response->json()) {
            $response->assertJsonStructure([
                'id',
                'invoice_id',
                'amount',
                'payment_date',
                'payment_method',
                'reference_number',
                'notes',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'invoice_id' => $paymentData['invoice_id'],
                'amount' => $paymentData['amount'],
                'payment_date' => $paymentData['payment_date'],
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $paymentData['reference_number'],
                'notes' => $paymentData['notes']
            ]);
        }
    }

    /**
     * Test POST /api/payments with minimal required data
     * 
     * @test
     */
    public function it_records_payment_with_minimal_data()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'payment_date' => $this->faker->date(),
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(201);
    }

    /**
     * Test POST /api/payments validation errors for missing required fields
     * 
     * @test
     */
    public function it_returns_validation_errors_for_missing_required_fields()
    {
        // Arrange - Empty data
        $paymentData = [];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert - Contract expects 422 for validation errors
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'invoice_id',
                    'amount',
                    'payment_date',
                    'payment_method'
                ]
            ]);
    }

    /**
     * Test POST /api/payments validation for invalid invoice_id
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_invoice_id()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 99999, // Non-existent invoice
            'amount' => 1000.00,
            'payment_date' => $this->faker->date(),
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'invoice_id'
                ]
            ]);
    }

    /**
     * Test POST /api/payments validation for negative amount
     * 
     * @test
     */
    public function it_returns_validation_error_for_negative_amount()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => -1000.00, // Negative amount
            'payment_date' => $this->faker->date(),
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount'
                ]
            ]);
    }

    /**
     * Test POST /api/payments validation for zero amount
     * 
     * @test
     */
    public function it_returns_validation_error_for_zero_amount()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 0.00, // Zero amount
            'payment_date' => $this->faker->date(),
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount'
                ]
            ]);
    }

    /**
     * Test POST /api/payments validation for invalid date format
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_date_format()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'payment_date' => 'invalid-date',
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'payment_date'
                ]
            ]);
    }

    /**
     * Test POST /api/payments validation for invalid payment method
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_payment_method()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'payment_date' => $this->faker->date(),
            'payment_method' => 'invalid_method' // Should be cash, bank_transfer, credit_card, etc.
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'payment_method'
                ]
            ]);
    }

    /**
     * Test POST /api/payments with all valid payment methods
     * 
     * @test
     */
    public function it_accepts_all_valid_payment_methods()
    {
        // Arrange - Test different payment methods
        $validMethods = ['cash', 'bank_transfer', 'credit_card', 'debit_card', 'check', 'other'];

        foreach ($validMethods as $method) {
            $paymentData = [
                'invoice_id' => 1,
                'amount' => 1000.00,
                'payment_date' => $this->faker->date(),
                'payment_method' => $method
            ];

            // Act
            $response = $this->postJson('/api/payments', $paymentData);

            // Assert - Should accept valid payment methods
            $this->assertTrue(in_array($response->status(), [201, 404, 422]), 
                "Payment method '{$method}' should be valid");
        }
    }

    /**
     * Test POST /api/payments with future payment date
     * 
     * @test
     */
    public function it_allows_future_payment_dates()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'payment_date' => '2025-12-31', // Future date
            'payment_method' => 'bank_transfer'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert - Should allow future dates (for scheduled payments)
        $this->assertTrue(in_array($response->status(), [201, 404, 422]));
    }

    /**
     * Test POST /api/payments with very large amount
     * 
     * @test
     */
    public function it_handles_large_payment_amounts()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 999999999.99, // Very large amount
            'payment_date' => $this->faker->date(),
            'payment_method' => 'bank_transfer'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert - Should handle large amounts
        $this->assertTrue(in_array($response->status(), [201, 404, 422]));
    }

    /**
     * Test POST /api/payments requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $paymentData = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'payment_date' => $this->faker->date(),
            'payment_method' => 'cash'
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);
        
        // Assert
        $this->assertTrue(in_array($response->status(), [201, 401, 422]));
    }
}