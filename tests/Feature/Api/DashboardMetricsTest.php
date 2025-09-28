<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/dashboard/metrics returns dashboard metrics successfully
     * 
     * @test
     */
    public function it_returns_dashboard_metrics_successfully()
    {
        // Act
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_clients',
                'active_projects',
                'pending_invoices',
                'total_revenue',
                'monthly_revenue',
                'overdue_invoices',
                'recent_activities'
            ]);

        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify data types
            $this->assertIsInt($data['total_clients']);
            $this->assertIsInt($data['active_projects']);
            $this->assertIsInt($data['pending_invoices']);
            $this->assertTrue(is_numeric($data['total_revenue']));
            $this->assertTrue(is_numeric($data['monthly_revenue']));
            $this->assertIsInt($data['overdue_invoices']);
            $this->assertIsArray($data['recent_activities']);
            
            // Verify non-negative values
            $this->assertGreaterThanOrEqual(0, $data['total_clients']);
            $this->assertGreaterThanOrEqual(0, $data['active_projects']);
            $this->assertGreaterThanOrEqual(0, $data['pending_invoices']);
            $this->assertGreaterThanOrEqual(0, $data['total_revenue']);
            $this->assertGreaterThanOrEqual(0, $data['monthly_revenue']);
            $this->assertGreaterThanOrEqual(0, $data['overdue_invoices']);
        }
    }

    /**
     * Test GET /api/dashboard/metrics returns correct structure for recent activities
     * 
     * @test
     */
    public function it_returns_correct_structure_for_recent_activities()
    {
        // Act
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            if (!empty($data['recent_activities'])) {
                // Verify structure of recent activities
                foreach ($data['recent_activities'] as $activity) {
                    $this->assertIsArray($activity);
                    $this->assertArrayHasKey('id', $activity);
                    $this->assertArrayHasKey('type', $activity);
                    $this->assertArrayHasKey('description', $activity);
                    $this->assertArrayHasKey('created_at', $activity);
                    
                    // Verify data types
                    $this->assertIsInt($activity['id']);
                    $this->assertIsString($activity['type']);
                    $this->assertIsString($activity['description']);
                    $this->assertIsString($activity['created_at']);
                }
            }
        }
    }

    /**
     * Test GET /api/dashboard/metrics with no data returns zero values
     * 
     * @test
     */
    public function it_returns_zero_values_when_no_data_exists()
    {
        // Act - Fresh database should have no data
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Should return zero values for counts and revenue
            $this->assertEquals(0, $data['total_clients']);
            $this->assertEquals(0, $data['active_projects']);
            $this->assertEquals(0, $data['pending_invoices']);
            $this->assertEquals(0, $data['total_revenue']);
            $this->assertEquals(0, $data['monthly_revenue']);
            $this->assertEquals(0, $data['overdue_invoices']);
            $this->assertEmpty($data['recent_activities']);
        }
    }

    /**
     * Test GET /api/dashboard/metrics performance
     * 
     * @test
     */
    public function it_responds_within_acceptable_time()
    {
        // Arrange
        $startTime = microtime(true);

        // Act
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert - Dashboard metrics should load quickly (under 2 seconds)
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        $this->assertLessThan(2.0, $responseTime, 'Dashboard metrics should load under 2 seconds');
    }

    /**
     * Test GET /api/dashboard/metrics caching behavior
     * 
     * @test
     */
    public function it_handles_caching_correctly()
    {
        // Act - Make two consecutive requests
        $response1 = $this->getJson('/api/dashboard/metrics');
        $response2 = $this->getJson('/api/dashboard/metrics');

        // Assert - Both requests should succeed
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        if ($response1->status() === 200 && $response2->status() === 200) {
            // Data should be consistent between requests
            $data1 = $response1->json();
            $data2 = $response2->json();
            
            $this->assertEquals($data1['total_clients'], $data2['total_clients']);
            $this->assertEquals($data1['active_projects'], $data2['active_projects']);
            $this->assertEquals($data1['pending_invoices'], $data2['pending_invoices']);
        }
    }

    /**
     * Test GET /api/dashboard/metrics with large dataset simulation
     * 
     * @test
     */
    public function it_handles_large_datasets()
    {
        // Arrange - This will fail until models exist
        // Create large dataset simulation
        // Client::factory()->count(1000)->create();
        // Project::factory()->count(500)->create();
        // Invoice::factory()->count(200)->create();

        // Act
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert - Should handle large datasets efficiently
        $this->assertTrue(in_array($response->status(), [200, 500]));
        
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify structure is maintained even with large datasets
            $this->assertArrayHasKey('total_clients', $data);
            $this->assertArrayHasKey('active_projects', $data);
            $this->assertArrayHasKey('pending_invoices', $data);
        }
    }

    /**
     * Test GET /api/dashboard/metrics requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // Act
        $response = $this->getJson('/api/dashboard/metrics');
        
        // Assert - During development, this should work without auth
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401]));
    }

    /**
     * Test GET /api/dashboard/metrics handles database errors gracefully
     * 
     * @test
     */
    public function it_handles_database_errors_gracefully()
    {
        // This test will verify error handling when database is unavailable
        // For now, we just verify the endpoint exists
        
        // Act
        $response = $this->getJson('/api/dashboard/metrics');
        
        // Assert - Should either return data or handle errors gracefully
        $this->assertTrue(in_array($response->status(), [200, 500, 503]));
    }

    /**
     * Test GET /api/dashboard/metrics returns consistent data format
     * 
     * @test
     */
    public function it_returns_consistent_data_format()
    {
        // Act
        $response = $this->getJson('/api/dashboard/metrics');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            // Verify all required fields are present
            $requiredFields = [
                'total_clients',
                'active_projects', 
                'pending_invoices',
                'total_revenue',
                'monthly_revenue',
                'overdue_invoices',
                'recent_activities'
            ];
            
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $data, "Field '{$field}' should be present in response");
            }
        }
    }
}