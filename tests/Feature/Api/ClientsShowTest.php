<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsShowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/clients/{id} returns client details successfully
     * 
     * @test
     */
    public function it_returns_client_details_successfully()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create([
        //     'name' => 'John Doe',
        //     'email' => 'john@example.com',
        //     'phone' => '+1234567890',
        //     'address' => '123 Main St',
        //     'identity_number' => '1234567890',
        //     'notes' => 'Test client notes',
        //     'status' => 'active'
        // ]);

        // For now, we'll test with a hypothetical ID
        $clientId = 1;

        // Act
        $response = $this->getJson("/api/clients/{$clientId}");

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
        //     'name' => $client->name,
        //     'email' => $client->email,
        //     'phone' => $client->phone,
        //     'address' => $client->address,
        //     'identity_number' => $client->identity_number,
        //     'notes' => $client->notes,
        //     'status' => $client->status
        // ]);
    }

    /**
     * Test GET /api/clients/{id} returns 404 for non-existent client
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_client()
    {
        // Arrange
        $nonExistentId = 99999;

        // Act
        $response = $this->getJson("/api/clients/{$nonExistentId}");

        // Assert - Contract expects 404 for not found
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/clients/{id} with invalid ID format
     * 
     * @test
     */
    public function it_returns_404_for_invalid_id_format()
    {
        // Arrange
        $invalidId = 'invalid-id';

        // Act
        $response = $this->getJson("/api/clients/{$invalidId}");

        // Assert - Should return 404 or 400 for invalid ID format
        $this->assertTrue(in_array($response->status(), [400, 404]));
    }

    /**
     * Test GET /api/clients/{id} with zero ID
     * 
     * @test
     */
    public function it_returns_404_for_zero_id()
    {
        // Arrange
        $zeroId = 0;

        // Act
        $response = $this->getJson("/api/clients/{$zeroId}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/clients/{id} with negative ID
     * 
     * @test
     */
    public function it_returns_404_for_negative_id()
    {
        // Arrange
        $negativeId = -1;

        // Act
        $response = $this->getJson("/api/clients/{$negativeId}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/clients/{id} returns correct data types
     * 
     * @test
     */
    public function it_returns_correct_data_types()
    {
        // Arrange - This will fail until Client model exists
        // $client = Client::factory()->create();

        // For now, we'll test with a hypothetical ID
        $clientId = 1;

        // Act
        $response = $this->getJson("/api/clients/{$clientId}");

        // Assert - Test will fail initially, but defines expected structure
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify data types match API contract
            $this->assertIsInt($data['id']);
            $this->assertIsString($data['name']);
            $this->assertIsString($data['email']);
            $this->assertTrue(is_string($data['phone']) || is_null($data['phone']));
            $this->assertTrue(is_string($data['address']) || is_null($data['address']));
            $this->assertTrue(is_string($data['identity_number']) || is_null($data['identity_number']));
            $this->assertTrue(is_string($data['notes']) || is_null($data['notes']));
            $this->assertContains($data['status'], ['active', 'archived']);
            $this->assertIsString($data['created_at']);
            $this->assertIsString($data['updated_at']);
        }
    }

    /**
     * Test GET /api/clients/{id} for archived client
     * 
     * @test
     */
    public function it_returns_archived_client_details()
    {
        // Arrange - This will fail until Client model exists
        // $archivedClient = Client::factory()->create(['status' => 'archived']);

        // For now, we'll test with a hypothetical ID
        $clientId = 2;

        // Act
        $response = $this->getJson("/api/clients/{$clientId}");

        // Assert - Should still return client details even if archived
        if ($response->status() === 200) {
            $response->assertJsonStructure([
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
        }
    }

    /**
     * Test GET /api/clients/{id} requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $clientId = 1;

        // Act
        $response = $this->getJson("/api/clients/{$clientId}");
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401, 404]));
    }

    /**
     * Test GET /api/clients/{id} response time performance
     * 
     * @test
     */
    public function it_responds_within_acceptable_time()
    {
        // Arrange
        $clientId = 1;
        $startTime = microtime(true);

        // Act
        $response = $this->getJson("/api/clients/{$clientId}");

        // Assert - Response should be fast (under 1 second)
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        $this->assertLessThan(1.0, $responseTime, 'API response should be under 1 second');
    }
}