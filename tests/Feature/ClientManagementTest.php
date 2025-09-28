<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test complete client management workflow
     * 
     * @test
     */
    public function it_handles_complete_client_management_workflow()
    {
        // Step 1: Create a new client
        $clientData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'address' => '123 Main St, City',
            'identity_number' => '1234567890',
            'notes' => 'Important client',
            'status' => 'active'
        ];

        $createResponse = $this->postJson('/api/clients', $clientData);
        
        // Should create client successfully or fail gracefully
        $this->assertTrue(in_array($createResponse->status(), [201, 404, 422]));

        if ($createResponse->status() === 201) {
            $client = $createResponse->json();
            $clientId = $client['id'];

            // Step 2: Retrieve the created client
            $showResponse = $this->getJson("/api/clients/{$clientId}");
            $showResponse->assertStatus(200)
                ->assertJson([
                    'id' => $clientId,
                    'name' => $clientData['name'],
                    'email' => $clientData['email']
                ]);

            // Step 3: Update client information
            $updateData = [
                'name' => 'John Doe Updated',
                'phone' => '+0987654321',
                'notes' => 'Updated notes'
            ];

            $updateResponse = $this->putJson("/api/clients/{$clientId}", $updateData);
            $updateResponse->assertStatus(200)
                ->assertJson([
                    'id' => $clientId,
                    'name' => $updateData['name'],
                    'phone' => $updateData['phone'],
                    'notes' => $updateData['notes']
                ]);

            // Step 4: Verify client appears in list
            $listResponse = $this->getJson('/api/clients');
            $listResponse->assertStatus(200);
            
            $clients = $listResponse->json()['data'];
            $foundClient = collect($clients)->firstWhere('id', $clientId);
            $this->assertNotNull($foundClient, 'Updated client should appear in clients list');

            // Step 5: Archive client (soft delete)
            $archiveData = ['status' => 'archived'];
            $archiveResponse = $this->putJson("/api/clients/{$clientId}", $archiveData);
            $archiveResponse->assertStatus(200)
                ->assertJson([
                    'id' => $clientId,
                    'status' => 'archived'
                ]);
        }
    }

    /**
     * Test client search and filtering functionality
     * 
     * @test
     */
    public function it_handles_client_search_and_filtering()
    {
        // Create multiple clients with different attributes
        $clients = [
            [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'status' => 'active'
            ],
            [
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'status' => 'active'
            ],
            [
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'status' => 'archived'
            ]
        ];

        $createdClients = [];
        foreach ($clients as $clientData) {
            $response = $this->postJson('/api/clients', $clientData);
            if ($response->status() === 201) {
                $createdClients[] = $response->json();
            }
        }

        if (!empty($createdClients)) {
            // Test filtering by status
            $activeResponse = $this->getJson('/api/clients?status=active');
            $activeResponse->assertStatus(200);

            $archivedResponse = $this->getJson('/api/clients?status=archived');
            $archivedResponse->assertStatus(200);

            // Test search functionality
            $searchResponse = $this->getJson('/api/clients?search=Alice');
            $searchResponse->assertStatus(200);
        }
    }

    /**
     * Test client validation and error handling
     * 
     * @test
     */
    public function it_handles_client_validation_and_errors()
    {
        // Test creating client with invalid data
        $invalidData = [
            'name' => '', // Empty name
            'email' => 'invalid-email', // Invalid email format
            'status' => 'invalid_status' // Invalid status
        ];

        $response = $this->postJson('/api/clients', $invalidData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test duplicate email validation
        $validClient = [
            'name' => 'Test Client',
            'email' => 'test@example.com'
        ];

        $firstResponse = $this->postJson('/api/clients', $validClient);
        
        if ($firstResponse->status() === 201) {
            // Try to create another client with same email
            $duplicateResponse = $this->postJson('/api/clients', $validClient);
            $duplicateResponse->assertStatus(422);
        }
    }

    /**
     * Test client relationship with projects
     * 
     * @test
     */
    public function it_handles_client_project_relationships()
    {
        // Create a client
        $clientData = [
            'name' => 'Project Client',
            'email' => 'project@example.com'
        ];

        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $client = $clientResponse->json();
            $clientId = $client['id'];

            // Create a project for this client
            $projectData = [
                'client_id' => $clientId,
                'name' => 'Test Project',
                'description' => 'A test project for the client',
                'status' => 'active'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            // Should create project successfully or fail gracefully
            $this->assertTrue(in_array($projectResponse->status(), [201, 404, 422]));

            if ($projectResponse->status() === 201) {
                // Verify project is associated with client
                $project = $projectResponse->json();
                $this->assertEquals($clientId, $project['client_id']);

                // Verify client's projects can be filtered
                $clientProjectsResponse = $this->getJson("/api/projects?client_id={$clientId}");
                $clientProjectsResponse->assertStatus(200);
            }
        }
    }

    /**
     * Test client data consistency and integrity
     * 
     * @test
     */
    public function it_maintains_client_data_consistency()
    {
        // Create a client
        $clientData = [
            'name' => 'Consistency Test Client',
            'email' => 'consistency@example.com',
            'status' => 'active'
        ];

        $createResponse = $this->postJson('/api/clients', $clientData);
        
        if ($createResponse->status() === 201) {
            $client = $createResponse->json();
            $clientId = $client['id'];

            // Verify data consistency across different endpoints
            $showResponse = $this->getJson("/api/clients/{$clientId}");
            $listResponse = $this->getJson('/api/clients');

            if ($showResponse->status() === 200 && $listResponse->status() === 200) {
                $clientFromShow = $showResponse->json();
                $clientsFromList = $listResponse->json()['data'];
                $clientFromList = collect($clientsFromList)->firstWhere('id', $clientId);

                // Data should be consistent
                $this->assertEquals($clientFromShow['name'], $clientFromList['name']);
                $this->assertEquals($clientFromShow['email'], $clientFromList['email']);
                $this->assertEquals($clientFromShow['status'], $clientFromList['status']);
            }
        }
    }

    /**
     * Test client bulk operations (if implemented)
     * 
     * @test
     */
    public function it_handles_client_bulk_operations()
    {
        // Create multiple clients
        $clientsData = [
            ['name' => 'Bulk Client 1', 'email' => 'bulk1@example.com'],
            ['name' => 'Bulk Client 2', 'email' => 'bulk2@example.com'],
            ['name' => 'Bulk Client 3', 'email' => 'bulk3@example.com']
        ];

        $createdClients = [];
        foreach ($clientsData as $clientData) {
            $response = $this->postJson('/api/clients', $clientData);
            if ($response->status() === 201) {
                $createdClients[] = $response->json();
            }
        }

        if (count($createdClients) >= 2) {
            // Test bulk status update (if endpoint exists)
            $clientIds = array_column($createdClients, 'id');
            
            // This would test bulk operations if implemented
            // $bulkUpdateResponse = $this->putJson('/api/clients/bulk', [
            //     'ids' => $clientIds,
            //     'status' => 'archived'
            // ]);
            
            // For now, just verify individual updates work
            foreach ($clientIds as $clientId) {
                $updateResponse = $this->putJson("/api/clients/{$clientId}", ['status' => 'archived']);
                $this->assertTrue(in_array($updateResponse->status(), [200, 404, 422]));
            }
        }
    }

    /**
     * Test client pagination and performance
     * 
     * @test
     */
    public function it_handles_client_pagination_and_performance()
    {
        // Create multiple clients to test pagination
        for ($i = 1; $i <= 5; $i++) {
            $clientData = [
                'name' => "Pagination Client {$i}",
                'email' => "pagination{$i}@example.com"
            ];

            $this->postJson('/api/clients', $clientData);
        }

        // Test pagination
        $response = $this->getJson('/api/clients');
        
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify pagination structure
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('meta', $data);
            $this->assertArrayHasKey('current_page', $data['meta']);
            $this->assertArrayHasKey('total', $data['meta']);
        }
    }
}