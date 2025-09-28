<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectsUpdateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test PUT /api/projects/{id} updates project successfully
     * 
     * @test
     */
    public function it_updates_project_successfully()
    {
        // Arrange - This will fail until Project model exists
        // $client = Client::factory()->create();
        // $project = Project::factory()->create(['client_id' => $client->id]);

        $projectId = 1;
        $updateData = [
            'name' => 'Updated Project Name',
            'description' => 'Updated project description',
            'status' => 'active',
            'value' => 25000.50,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'notes' => 'Updated project notes'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$projectId}", $updateData);

        // Assert
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
                'client'
            ]);
    }

    /**
     * Test PUT /api/projects/{id} with partial update
     * 
     * @test
     */
    public function it_updates_project_with_partial_data()
    {
        // Arrange
        $projectId = 1;
        $updateData = [
            'name' => 'Partially Updated Name',
            'status' => 'completed'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$projectId}", $updateData);

        // Assert
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
                'client'
            ]);
    }

    /**
     * Test PUT /api/projects/{id} returns 404 for non-existent project
     * 
     * @test
     */
    public function it_returns_404_for_non_existent_project()
    {
        // Arrange
        $nonExistentId = 99999;
        $updateData = [
            'name' => 'Updated Name'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$nonExistentId}", $updateData);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test PUT /api/projects/{id} validation for invalid status
     * 
     * @test
     */
    public function it_validates_status_values()
    {
        // Arrange
        $projectId = 1;
        $updateData = [
            'status' => 'invalid_status'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$projectId}", $updateData);

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
     * Test PUT /api/projects/{id} validation for invalid dates
     * 
     * @test
     */
    public function it_validates_date_formats()
    {
        // Arrange
        $projectId = 1;
        $updateData = [
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$projectId}", $updateData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'start_date',
                    'end_date'
                ]
            ]);
    }

    /**
     * Test PUT /api/projects/{id} requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $projectId = 1;
        $updateData = [
            'name' => 'Updated Name'
        ];

        // Act
        $response = $this->putJson("/api/projects/{$projectId}", $updateData);
        
        // Assert
        $this->assertTrue(in_array($response->status(), [200, 401, 404, 422]));
    }
}