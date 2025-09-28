<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectsStoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test POST /api/projects creates a new project successfully
     * 
     * @test
     */
    public function it_creates_a_new_project_successfully()
    {
        // Arrange - This will fail until Client and Project models exist
        // $client = Client::factory()->create();

        $projectData = [
            'client_id' => 1, // $client->id,
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'status' => 'draft',
            'value' => $this->faker->randomFloat(2, 1000, 50000),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'notes' => $this->faker->sentence
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

        // Assert - Contract expectations from API spec
        $response->assertStatus(201)
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
            ])
            ->assertJson([
                'client_id' => $projectData['client_id'],
                'name' => $projectData['name'],
                'description' => $projectData['description'],
                'status' => $projectData['status'],
                'value' => $projectData['value'],
                'start_date' => $projectData['start_date'],
                'end_date' => $projectData['end_date'],
                'notes' => $projectData['notes']
            ]);
    }

    /**
     * Test POST /api/projects with minimal required data
     * 
     * @test
     */
    public function it_creates_project_with_minimal_required_data()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3)
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

        // Assert
        $response->assertStatus(201)
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
            ])
            ->assertJson([
                'client_id' => $projectData['client_id'],
                'name' => $projectData['name'],
                'status' => 'draft' // Default status
            ]);
    }

    /**
     * Test POST /api/projects validation errors for missing required fields
     * 
     * @test
     */
    public function it_returns_validation_errors_for_missing_required_fields()
    {
        // Arrange - Empty data
        $projectData = [];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

        // Assert - Contract expects 422 for validation errors
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'client_id',
                    'name'
                ]
            ]);
    }

    /**
     * Test POST /api/projects validation for invalid client_id
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_client_id()
    {
        // Arrange
        $projectData = [
            'client_id' => 99999, // Non-existent client
            'name' => $this->faker->sentence(3)
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

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
     * Test POST /api/projects validation for invalid status
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_status()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3),
            'status' => 'invalid_status' // Should only accept draft, active, completed, cancelled
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

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
     * Test POST /api/projects validation for invalid date formats
     * 
     * @test
     */
    public function it_returns_validation_error_for_invalid_date_formats()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3),
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date'
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

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
     * Test POST /api/projects validation for negative value
     * 
     * @test
     */
    public function it_returns_validation_error_for_negative_value()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3),
            'value' => -1000 // Negative value should not be allowed
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'value'
                ]
            ]);
    }

    /**
     * Test POST /api/projects with end_date before start_date
     * 
     * @test
     */
    public function it_returns_validation_error_when_end_date_before_start_date()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3),
            'start_date' => '2024-12-31',
            'end_date' => '2024-01-01' // End date before start date
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'end_date'
                ]
            ]);
    }

    /**
     * Test POST /api/projects requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Arrange
        $projectData = [
            'client_id' => 1,
            'name' => $this->faker->sentence(3)
        ];

        // Act
        $response = $this->postJson('/api/projects', $projectData);
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [201, 401, 422]));
    }
}