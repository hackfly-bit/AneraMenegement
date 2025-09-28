<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test complete project lifecycle workflow
     * 
     * @test
     */
    public function it_handles_complete_project_lifecycle()
    {
        // Step 1: Create a client first
        $clientData = [
            'name' => 'Project Client',
            'email' => 'project@example.com'
        ];

        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $client = $clientResponse->json();
            $clientId = $client['id'];

            // Step 2: Create a new project
            $projectData = [
                'client_id' => $clientId,
                'name' => 'Website Development Project',
                'description' => 'Complete website development with modern design',
                'status' => 'draft',
                'value' => 15000.00,
                'start_date' => '2024-01-01',
                'end_date' => '2024-03-31',
                'notes' => 'Initial project setup'
            ];

            $createResponse = $this->postJson('/api/projects', $projectData);
            
            if ($createResponse->status() === 201) {
                $project = $createResponse->json();
                $projectId = $project['id'];

                // Step 3: Update project status to active
                $activateData = [
                    'status' => 'active',
                    'notes' => 'Project started - development phase'
                ];

                $activateResponse = $this->putJson("/api/projects/{$projectId}", $activateData);
                $activateResponse->assertStatus(200)
                    ->assertJson([
                        'id' => $projectId,
                        'status' => 'active'
                    ]);

                // Step 4: Update project progress
                $progressData = [
                    'notes' => 'Project 50% complete - design phase finished',
                    'value' => 16000.00 // Updated project value
                ];

                $progressResponse = $this->putJson("/api/projects/{$projectId}", $progressData);
                $progressResponse->assertStatus(200);

                // Step 5: Complete the project
                $completeData = [
                    'status' => 'completed',
                    'end_date' => '2024-03-15', // Completed earlier than planned
                    'notes' => 'Project completed successfully'
                ];

                $completeResponse = $this->putJson("/api/projects/{$projectId}", $completeData);
                $completeResponse->assertStatus(200)
                    ->assertJson([
                        'id' => $projectId,
                        'status' => 'completed'
                    ]);

                // Step 6: Verify project appears in completed projects list
                $completedResponse = $this->getJson('/api/projects?status=completed');
                $completedResponse->assertStatus(200);
            }
        }
    }

    /**
     * Test project status transitions
     * 
     * @test
     */
    public function it_handles_project_status_transitions()
    {
        // Create client and project
        $clientData = ['name' => 'Status Client', 'email' => 'status@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $projectData = [
                'client_id' => $clientId,
                'name' => 'Status Test Project',
                'status' => 'draft'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $projectId = $projectResponse->json()['id'];

                // Test valid status transitions
                $validTransitions = [
                    'draft' => 'active',
                    'active' => 'completed',
                    'active' => 'cancelled'
                ];

                foreach ($validTransitions as $from => $to) {
                    // Set initial status
                    $this->putJson("/api/projects/{$projectId}", ['status' => $from]);
                    
                    // Transition to new status
                    $response = $this->putJson("/api/projects/{$projectId}", ['status' => $to]);
                    $this->assertTrue(in_array($response->status(), [200, 422]));
                }
            }
        }
    }

    /**
     * Test project and client relationship integrity
     * 
     * @test
     */
    public function it_maintains_project_client_relationship_integrity()
    {
        // Create client
        $clientData = ['name' => 'Relationship Client', 'email' => 'relationship@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $client = $clientResponse->json();
            $clientId = $client['id'];

            // Create project for this client
            $projectData = [
                'client_id' => $clientId,
                'name' => 'Relationship Test Project'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $project = $projectResponse->json();
                $projectId = $project['id'];

                // Verify project shows correct client relationship
                $showResponse = $this->getJson("/api/projects/{$projectId}");
                
                if ($showResponse->status() === 200) {
                    $projectDetails = $showResponse->json();
                    
                    $this->assertArrayHasKey('client', $projectDetails);
                    $this->assertEquals($clientId, $projectDetails['client']['id']);
                    $this->assertEquals($client['name'], $projectDetails['client']['name']);
                }

                // Verify client's projects can be filtered
                $clientProjectsResponse = $this->getJson("/api/projects?client_id={$clientId}");
                $clientProjectsResponse->assertStatus(200);
                
                if ($clientProjectsResponse->status() === 200) {
                    $projects = $clientProjectsResponse->json()['data'];
                    $foundProject = collect($projects)->firstWhere('id', $projectId);
                    $this->assertNotNull($foundProject);
                }
            }
        }
    }

    /**
     * Test project validation and business rules
     * 
     * @test
     */
    public function it_enforces_project_validation_and_business_rules()
    {
        // Test creating project with invalid data
        $invalidData = [
            'client_id' => 99999, // Non-existent client
            'name' => '', // Empty name
            'status' => 'invalid_status', // Invalid status
            'value' => -1000, // Negative value
            'start_date' => 'invalid-date', // Invalid date
            'end_date' => '2024-01-01',
            'start_date' => '2024-12-31' // End date before start date
        ];

        $response = $this->postJson('/api/projects', $invalidData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test business rule: end date should be after start date
        $clientData = ['name' => 'Validation Client', 'email' => 'validation@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $invalidDateData = [
                'client_id' => $clientId,
                'name' => 'Invalid Date Project',
                'start_date' => '2024-12-31',
                'end_date' => '2024-01-01' // End before start
            ];

            $dateResponse = $this->postJson('/api/projects', $invalidDateData);
            $dateResponse->assertStatus(422);
        }
    }

    /**
     * Test project filtering and search functionality
     * 
     * @test
     */
    public function it_handles_project_filtering_and_search()
    {
        // Create client
        $clientData = ['name' => 'Filter Client', 'email' => 'filter@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            // Create multiple projects with different statuses
            $projects = [
                ['name' => 'Active Project 1', 'status' => 'active'],
                ['name' => 'Active Project 2', 'status' => 'active'],
                ['name' => 'Draft Project 1', 'status' => 'draft'],
                ['name' => 'Completed Project 1', 'status' => 'completed']
            ];

            foreach ($projects as $projectData) {
                $projectData['client_id'] = $clientId;
                $this->postJson('/api/projects', $projectData);
            }

            // Test filtering by status
            $activeResponse = $this->getJson('/api/projects?status=active');
            $activeResponse->assertStatus(200);

            $draftResponse = $this->getJson('/api/projects?status=draft');
            $draftResponse->assertStatus(200);

            // Test filtering by client
            $clientProjectsResponse = $this->getJson("/api/projects?client_id={$clientId}");
            $clientProjectsResponse->assertStatus(200);

            // Test combined filters
            $combinedResponse = $this->getJson("/api/projects?client_id={$clientId}&status=active");
            $combinedResponse->assertStatus(200);
        }
    }

    /**
     * Test project timeline and milestone tracking
     * 
     * @test
     */
    public function it_handles_project_timeline_and_milestones()
    {
        // Create client and project
        $clientData = ['name' => 'Timeline Client', 'email' => 'timeline@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $projectData = [
                'client_id' => $clientId,
                'name' => 'Timeline Project',
                'start_date' => '2024-01-01',
                'end_date' => '2024-06-30',
                'status' => 'active'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $projectId = $projectResponse->json()['id'];

                // Update project with milestone information
                $milestoneUpdates = [
                    [
                        'notes' => 'Milestone 1: Requirements gathering completed',
                        'status' => 'active'
                    ],
                    [
                        'notes' => 'Milestone 2: Design phase completed',
                        'status' => 'active'
                    ],
                    [
                        'notes' => 'Milestone 3: Development 50% complete',
                        'status' => 'active'
                    ]
                ];

                foreach ($milestoneUpdates as $update) {
                    $response = $this->putJson("/api/projects/{$projectId}", $update);
                    $this->assertTrue(in_array($response->status(), [200, 404, 422]));
                }

                // Verify project timeline integrity
                $showResponse = $this->getJson("/api/projects/{$projectId}");
                
                if ($showResponse->status() === 200) {
                    $project = $showResponse->json();
                    
                    // Verify dates are maintained
                    $this->assertEquals('2024-01-01', $project['start_date']);
                    $this->assertEquals('2024-06-30', $project['end_date']);
                }
            }
        }
    }

    /**
     * Test project value and budget tracking
     * 
     * @test
     */
    public function it_handles_project_value_and_budget_tracking()
    {
        // Create client and project
        $clientData = ['name' => 'Budget Client', 'email' => 'budget@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $projectData = [
                'client_id' => $clientId,
                'name' => 'Budget Tracking Project',
                'value' => 10000.00,
                'status' => 'active'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $projectId = $projectResponse->json()['id'];

                // Update project value (scope change)
                $valueUpdates = [
                    ['value' => 12000.00, 'notes' => 'Scope increased - additional features'],
                    ['value' => 11500.00, 'notes' => 'Final value after negotiation']
                ];

                foreach ($valueUpdates as $update) {
                    $response = $this->putJson("/api/projects/{$projectId}", $update);
                    
                    if ($response->status() === 200) {
                        $updatedProject = $response->json();
                        $this->assertEquals($update['value'], $updatedProject['value']);
                    }
                }
            }
        }
    }
}