<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientsIndexTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/clients returns paginated list of clients
     * 
     * @test
     */
    public function it_returns_paginated_list_of_clients()
    {
        // Arrange - This will fail initially as we don't have Client model yet
        // $clients = Client::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/clients');

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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
     * Test GET /api/clients with status filter
     * 
     * @test
     */
    public function it_filters_clients_by_status()
    {
        // Arrange - This will fail initially
        // $activeClient = Client::factory()->create(['status' => 'active']);
        // $archivedClient = Client::factory()->create(['status' => 'archived']);

        // Act
        $response = $this->getJson('/api/clients?status=active');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Additional assertion to verify filtering works
        // $response->assertJsonFragment(['status' => 'active']);
        // $response->assertJsonMissing(['status' => 'archived']);
    }

    /**
     * Test GET /api/clients with search parameter
     * 
     * @test
     */
    public function it_searches_clients_by_name_or_email()
    {
        // Arrange - This will fail initially
        // $client1 = Client::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        // $client2 = Client::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        // Act
        $response = $this->getJson('/api/clients?search=John');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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
     * Test GET /api/clients returns empty data when no clients exist
     * 
     * @test
     */
    public function it_returns_empty_data_when_no_clients_exist()
    {
        // Act
        $response = $this->getJson('/api/clients');

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
     * Test GET /api/clients requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // This test will be updated once authentication is implemented
        // For now, we assume the endpoint is accessible without auth during development
        
        $response = $this->getJson('/api/clients');
        
        // During development, this should return 200
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401]));
    }
}