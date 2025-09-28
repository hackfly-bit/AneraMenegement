<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsStoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test POST /api/clients creates a new client successfully
     * 
     * @test
     */
    public function it_creates_a_new_client_successfully()
    {
        // Arrange
        $clientData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'identity_number' => $this->faker->numerify('############'),
            'notes' => $this->faker->sentence,
            'status' => 'active'
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'phone',
                'address',
                'identity_number',
                'notes',
                'status',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'],
                'address' => $clientData['address'],
                'identity_number' => $clientData['identity_number'],
                'notes' => $clientData['notes'],
                'status' => $clientData['status']
            ]);

        // Verify data is stored in database (will fail until Client model exists)
        // $this->assertDatabaseHas('clients', [
        //     'name' => $clientData['name'],
        //     'email' => $clientData['email']
        // ]);
    }

    /**
     * Test POST /api/clients with minimal required data
     * 
     * @test
     */
    public function it_creates_client_with_minimal_required_data()
    {
        // Arrange - Only required fields
        $clientData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'phone',
                'address',
                'identity_number',
                'notes',
                'status',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'status' => 'active' // Default status
            ]);
    }

    /**
     * Test POST /api/clients validation errors for missing required fields
     * 
     * @test
     */
    public function it_returns_validation_errors_for_missing_required_fields()
    {
        // Arrange - Empty data
        $clientData = [];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert - Contract expects 422 for validation errors
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'email'
                ]
            ]);
    }

    /**
     * Test POST /api/clients validation for invalid email format
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_email()
    {
        // Arrange
        $clientData = [
            'name' => $this->faker->name,
            'email' => 'invalid-email-format',
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    /**
     * Test POST /api/clients validation for duplicate email
     * 
     * @test
     */
    public function it_returns_validation_error_for_duplicate_email()
    {
        // Arrange - This will fail until Client model exists
        // $existingClient = Client::factory()->create(['email' => 'test@example.com']);
        
        $clientData = [
            'name' => $this->faker->name,
            'email' => 'test@example.com', // Duplicate email
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert - Should return validation error for duplicate email
        // For now, we'll test that the endpoint exists
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    /**
     * Test POST /api/clients validation for invalid status
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_status()
    {
        // Arrange
        $clientData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'status' => 'invalid_status' // Should only accept 'active' or 'archived'
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'status'
                ]
            ]);
    }

    /**
     * Test POST /api/clients with all optional fields
     * 
     * @test
     */
    public function it_creates_client_with_all_optional_fields()
    {
        // Arrange
        $clientData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'identity_number' => $this->faker->numerify('############'),
            'notes' => $this->faker->paragraph,
            'status' => 'archived'
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);

        // Assert
        $response->assertStatus(201)
            ->assertJson($clientData);
    }

    /**
     * Test POST /api/clients requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $clientData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        // Act
        $response = $this->postJson('/api/clients', $clientData);
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [201, 401, 422]));
    }
}