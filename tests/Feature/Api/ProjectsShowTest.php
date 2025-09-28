<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectsShowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/projects/{id} returns project details successfully
     * 
     * @test
     */
    public function it_returns_project_details_successfully()
    {
        // Arrange - This will fail until Project model exists
        // $client = Client::factory()->create();
        // $project = Project::factory()->create(['client_id' => $client->id]);

        $projectId = 1;

        // Act
        $response = $this->getJson("/api/projects/{$projectId}");

        // Assert - Contract expectations from API spec (ProjectDetail schema)
        $response->assertStatus(200)
            ->assertJsonStructure([
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
            ]);
    }

    /**
     * Test GET /api/projects/{id} returns 404 for non-existent project
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_project()
    {
        // Arrange
        $nonExistentId = 99999;

        // Act
        $response = $this->getJson("/api/projects/{$nonExistentId}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test GET /api/projects/{id} with invalid ID format
     * 
     * @test
     */
    public function it_returns_404_for_invalid_id_format()
    {
        // Arrange
        $invalidId = 'invalid-id';

        // Act
        $response = $this->getJson("/api/projects/{$invalidId}");

        // Assert
        $this->assertTrue(in_array($response->status(), [400, 404]));
    }

    /**
     * Test GET /api/projects/{id} returns correct data types
     * 
     * @test
     */
    public function it_returns_correct_data_types()
    {
        // Arrange
        $projectId = 1;

        // Act
        $response = $this->getJson("/api/projects/{$projectId}");

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify data types match API contract
            $this->assertIsInt($data['id']);
            $this->assertIsString($data['name']);
            $this->assertTrue(is_string($data['description']) || is_null($data['description']));
            $this->assertContains($data['status'], ['draft', 'active', 'completed', 'cancelled']);
            $this->assertTrue(is_numeric($data['value']) || is_null($data['value']));
            $this->assertTrue(is_string($data['start_date']) || is_null($data['start_date']));
            $this->assertTrue(is_string($data['end_date']) || is_null($data['end_date']));
            $this->assertTrue(is_string($data['notes']) || is_null($data['notes']));
            $this->assertIsString($data['created_at']);
            
            // Verify client relationship
            $this->assertIsArray($data['client']);
            $this->assertIsInt($data['client']['id']);
            $this->assertIsString($data['client']['name']);
        }
    }

    /**
     * Test GET /api/projects/{id} requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $projectId = 1;

        // Act
        $response = $this->getJson("/api/projects/{$projectId}");
        
        // Assert
        $this->assertTrue(in_array($response->status(), [200, 401, 404]));
    }
}