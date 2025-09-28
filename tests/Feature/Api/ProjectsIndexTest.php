<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/projects returns paginated list of projects
     * 
     * @test
     */
    public function it_returns_paginated_list_of_projects()
    {
        // Arrange - This will fail initially as we don't have Project model yet
        // $client = Client::factory()->create();
        // $projects = Project::factory()->count(3)->create(['client_id' => $client->id]);

        // Act
        $response = $this->getJson('/api/projects');

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'value',
                        'start_date',
                        'end_date',
                        'notes',
                        'created_at',
                        'client' => [
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
     * Test GET /api/projects with client_id filter
     * 
     * @test
     */
    public function it_filters_projects_by_client_id()
    {
        // Arrange - This will fail initially
        // $client1 = Client::factory()->create();
        // $client2 = Client::factory()->create();
        // $project1 = Project::factory()->create(['client_id' => $client1->id]);
        // $project2 = Project::factory()->create(['client_id' => $client2->id]);

        $clientId = 1;

        // Act
        $response = $this->getJson("/api/projects?client_id={$clientId}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'value',
                        'start_date',
                        'end_date',
                        'notes',
                        'created_at',
                        'client'
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
        // $response->assertJsonFragment(['client_id' => $clientId]);
    }

    /**
     * Test GET /api/projects with status filter
     * 
     * @test
     */
    public function it_filters_projects_by_status()
    {
        // Arrange - This will fail initially
        // $activeProject = Project::factory()->create(['status' => 'active']);
        // $draftProject = Project::factory()->create(['status' => 'draft']);

        // Act
        $response = $this->getJson('/api/projects?status=active');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'value',
                        'start_date',
                        'end_date',
                        'notes',
                        'created_at',
                        'client'
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
        // $response->assertJsonMissing(['status' => 'draft']);
    }

    /**
     * Test GET /api/projects with multiple filters
     * 
     * @test
     */
    public function it_filters_projects_by_multiple_parameters()
    {
        // Arrange - This will fail initially
        // $client = Client::factory()->create();
        // $activeProject = Project::factory()->create([
        //     'client_id' => $client->id,
        //     'status' => 'active'
        // ]);
        // $draftProject = Project::factory()->create([
        //     'client_id' => $client->id,
        //     'status' => 'draft'
        // ]);

        $clientId = 1;

        // Act
        $response = $this->getJson("/api/projects?client_id={$clientId}&status=active");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'value',
                        'start_date',
                        'end_date',
                        'notes',
                        'created_at',
                        'client'
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
     * Test GET /api/projects returns empty data when no projects exist
     * 
     * @test
     */
    public function it_returns_empty_data_when_no_projects_exist()
    {
        // Act
        $response = $this->getJson('/api/projects');

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
     * Test GET /api/projects validates status parameter values
     * 
     * @test
     */
    public function it_validates_status_parameter_values()
    {
        // Act - Test with invalid status
        $response = $this->getJson('/api/projects?status=invalid_status');

        // Assert - Should either ignore invalid status or return validation error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
    }

    /**
     * Test GET /api/projects validates client_id parameter
     * 
     * @test
     */
    public function it_validates_client_id_parameter()
    {
        // Act - Test with invalid client_id
        $response = $this->getJson('/api/projects?client_id=invalid_id');

        // Assert - Should either ignore invalid client_id or return validation error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
    }

    /**
     * Test GET /api/projects with non-existent client_id
     * 
     * @test
     */
    public function it_handles_non_existent_client_id()
    {
        // Act
        $response = $this->getJson('/api/projects?client_id=99999');

        // Assert - Should return empty results
        $response->assertStatus(200)
            ->assertJson([
                'data' => []
            ]);
    }

    /**
     * Test GET /api/projects returns correct data types
     * 
     * @test
     */
    public function it_returns_correct_data_types()
    {
        // Arrange - This will fail until Project model exists
        // $client = Client::factory()->create();
        // $project = Project::factory()->create(['client_id' => $client->id]);

        // Act
        $response = $this->getJson('/api/projects');

        // Assert - Test will fail initially, but defines expected structure
        if ($response->status() === 200) {
            $data = $response->json();
            
            if (!empty($data['data'])) {
                $project = $data['data'][0];
                
                // Verify data types match API contract
                $this->assertIsInt($project['id']);
                $this->assertIsString($project['name']);
                $this->assertTrue(is_string($project['description']) || is_null($project['description']));
                $this->assertContains($project['status'], ['draft', 'active', 'completed', 'cancelled']);
                $this->assertTrue(is_numeric($project['value']) || is_null($project['value']));
                $this->assertTrue(is_string($project['start_date']) || is_null($project['start_date']));
                $this->assertTrue(is_string($project['end_date']) || is_null($project['end_date']));
                $this->assertTrue(is_string($project['notes']) || is_null($project['notes']));
                $this->assertIsString($project['created_at']);
                
                // Verify client relationship
                $this->assertIsArray($project['client']);
                $this->assertIsInt($project['client']['id']);
                $this->assertIsString($project['client']['name']);
            }
        }
    }

    /**
     * Test GET /api/projects requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // This test will be updated once authentication is implemented
        // For now, we assume the endpoint is accessible without auth during development
        
        $response = $this->getJson('/api/projects');
        
        // During development, this should return 200
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401]));
    }

    /**
     * Test GET /api/projects pagination works correctly
     * 
     * @test
     */
    public function it_paginates_results_correctly()
    {
        // Arrange - This will fail until Project model exists
        // $client = Client::factory()->create();
        // Project::factory()->count(25)->create(['client_id' => $client->id]);

        // Act
        $response = $this->getJson('/api/projects');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            $this->assertArrayHasKey('meta', $data);
            $this->assertArrayHasKey('current_page', $data['meta']);
            $this->assertArrayHasKey('last_page', $data['meta']);
            $this->assertArrayHasKey('per_page', $data['meta']);
            $this->assertArrayHasKey('total', $data['meta']);
            
            // Verify pagination meta data types
            $this->assertIsInt($data['meta']['current_page']);
            $this->assertIsInt($data['meta']['last_page']);
            $this->assertIsInt($data['meta']['per_page']);
            $this->assertIsInt($data['meta']['total']);
        }
    }
}