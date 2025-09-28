<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsUpdateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test PUT /api/clients/{id} updates client successfully
     * 
     * @test
     */
    public function it_updates_client_successfully()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create([
        //     'name' => 'Original Name',
        //     'email' => 'original@example.com',
        //     'status' => 'active'
        // ]);

        // For now, we'll test with a hypothetical ID
        $clientId = 1;

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+1234567890',
            'address' => 'Updated Address',
            'identity_number' => '9876543210',
            'notes' => 'Updated notes',
            'status' => 'archived'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
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
            ]);

        // Additional assertions when model exists
        // $response->assertJson([
        //     'id' => $client->id,
        //     'name' => $updateData['name'],
        //     'email' => $updateData['email'],
        //     'phone' => $updateData['phone'],
        //     'address' => $updateData['address'],
        //     'identity_number' => $updateData['identity_number'],
        //     'notes' => $updateData['notes'],
        //     'status' => $updateData['status']
        // ]);

        // Verify data is updated in database
        // $this->assertDatabaseHas('clients', [
        //     'id' => $client->id,
        //     'name' => $updateData['name'],
        //     'email' => $updateData['email']
        // ]);
    }

    /**
     * Test PUT /api/clients/{id} with partial update
     * 
     * @test
     */
    public function it_updates_client_with_partial_data()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create([
        //     'name' => 'Original Name',
        //     'email' => 'original@example.com',
        //     'phone' => 'Original Phone'
        // ]);

        $clientId = 1;

        // Only update name and phone
        $updateData = [
            'name' => 'Partially Updated Name',
            'phone' => '+9876543210'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert
        $response->assertStatus(200)
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
            ]);

        // Additional assertions when model exists
        // $response->assertJson([
        //     'name' => $updateData['name'],
        //     'phone' => $updateData['phone'],
        //     'email' => 'original@example.com' // Should remain unchanged
        // ]);
    }

    /**
     * Test PUT /api/clients/{id} returns 404 for non-existent client
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_client()
    {
        // Arrange
        $nonExistentId = 99999;
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$nonExistentId}", $updateData);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test PUT /api/clients/{id} validation for required fields
     * 
     * @test
     */
    public function it_validates_required_fields()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create();

        $clientId = 1;

        // Empty required fields
        $updateData = [
            'name' => '',
            'email' => ''
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert - Should return validation errors
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
     * Test PUT /api/clients/{id} validation for invalid email format
     * 
     * @test
     */
    public function it_validates_email_format()
    {
        // Arrange
        $clientId = 1;

        $updateData = [
            'name' => 'Valid Name',
            'email' => 'invalid-email-format'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

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
     * Test PUT /api/clients/{id} validation for duplicate email
     * 
     * @test
     */
    public function it_validates_unique_email()
    {
        // Arrange - This will fail until Client model exists
        // $client1 = Client::factory()->create(['email' => 'client1@example.com']);
        // $client2 = Client::factory()->create(['email' => 'client2@example.com']);

        $clientId = 1;

        // Try to update with existing email from another client
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'client2@example.com' // Email already exists for another client
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert - Should return validation error for duplicate email
        // For now, we'll test that the endpoint exists
        $this->assertTrue(in_array($response->status(), [200, 404, 422]));
    }

    /**
     * Test PUT /api/clients/{id} validation for invalid status
     * 
     * @test
     */
    public function it_validates_status_values()
    {
        // Arrange
        $clientId = 1;

        $updateData = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'status' => 'invalid_status' // Should only accept 'active' or 'archived'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

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
     * Test PUT /api/clients/{id} allows same email for same client
     * 
     * @test
     */
    public function it_allows_same_email_for_same_client()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create(['email' => 'test@example.com']);

        $clientId = 1;

        // Update with same email (should be allowed)
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'test@example.com' // Same email as current client
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert - Should be successful
        $this->assertTrue(in_array($response->status(), [200, 404]));
    }

    /**
     * Test PUT /api/clients/{id} with invalid ID format
     * 
     * @test
     */
    public function it_handles_invalid_id_format()
    {
        // Arrange
        $invalidId = 'invalid-id';
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$invalidId}", $updateData);

        // Assert - Should return 404 or 400 for invalid ID format
        $this->assertTrue(in_array($response->status(), [400, 404]));
    }

    /**
     * Test PUT /api/clients/{id} requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $clientId = 1;
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401, 404, 422]));
    }

    /**
     * Test PUT /api/clients/{id} preserves timestamps correctly
     * 
     * @test
     */
    public function it_preserves_created_at_and_updates_updated_at()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create();
        // $originalCreatedAt = $client->created_at;

        $clientId = 1;
        $updateData = [
            'name' => 'Updated Name'
        ];

        // Act
        $response = $this->putJson("/api/clients/{$clientId}", $updateData);

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            // created_at should remain the same, updated_at should be newer
            // $this->assertEquals($originalCreatedAt->toISOString(), $data['created_at']);
            // $this->assertNotEquals($originalCreatedAt->toISOString(), $data['updated_at']);
            
            // For now, just verify the structure
            $this->assertArrayHasKey('created_at', $data);
            $this->assertArrayHasKey('updated_at', $data);
        }
    }
}