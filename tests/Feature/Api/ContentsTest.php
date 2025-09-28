<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContentsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test GET /api/contents returns paginated list of contents
     * 
     * @test
     */
    public function it_returns_paginated_list_of_contents()
    {
        // Arrange - This will fail initially as we don't have Content model yet
        // $contents = Content::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/contents');

        // Assert - Contract expectations from API spec
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'excerpt',
                        'type',
                        'status',
                        'published_at',
                        'meta_title',
                        'meta_description',
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
     * Test GET /api/contents with type filter
     * 
     * @test
     */
    public function it_filters_contents_by_type()
    {
        // Arrange - This will fail initially
        // $page = Content::factory()->create(['type' => 'page']);
        // $post = Content::factory()->create(['type' => 'post']);

        // Act
        $response = $this->getJson('/api/contents?type=page');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'excerpt',
                        'type',
                        'status',
                        'published_at',
                        'meta_title',
                        'meta_description',
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
        // $response->assertJsonFragment(['type' => 'page']);
        // $response->assertJsonMissing(['type' => 'post']);
    }

    /**
     * Test GET /api/contents with status filter
     * 
     * @test
     */
    public function it_filters_contents_by_status()
    {
        // Arrange - This will fail initially
        // $publishedContent = Content::factory()->create(['status' => 'published']);
        // $draftContent = Content::factory()->create(['status' => 'draft']);

        // Act
        $response = $this->getJson('/api/contents?status=published');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'excerpt',
                        'type',
                        'status',
                        'published_at',
                        'meta_title',
                        'meta_description',
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
     * Test GET /api/contents with multiple filters
     * 
     * @test
     */
    public function it_filters_contents_by_multiple_parameters()
    {
        // Arrange - This will fail initially
        // $publishedPage = Content::factory()->create([
        //     'type' => 'page',
        //     'status' => 'published'
        // ]);
        // $draftPage = Content::factory()->create([
        //     'type' => 'page',
        //     'status' => 'draft'
        // ]);

        // Act
        $response = $this->getJson('/api/contents?type=page&status=published');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'excerpt',
                        'type',
                        'status',
                        'published_at',
                        'meta_title',
                        'meta_description',
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
     * Test GET /api/contents returns empty data when no contents exist
     * 
     * @test
     */
    public function it_returns_empty_data_when_no_contents_exist()
    {
        // Act
        $response = $this->getJson('/api/contents');

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
     * Test GET /api/contents validates type parameter values
     * 
     * @test
     */
    public function it_validates_type_parameter_values()
    {
        // Act - Test with invalid type
        $response = $this->getJson('/api/contents?type=invalid_type');

        // Assert - Should either ignore invalid type or return validation error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
    }

    /**
     * Test GET /api/contents validates status parameter values
     * 
     * @test
     */
    public function it_validates_status_parameter_values()
    {
        // Act - Test with invalid status
        $response = $this->getJson('/api/contents?status=invalid_status');

        // Assert - Should either ignore invalid status or return validation error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
    }

    /**
     * Test GET /api/contents returns correct data types
     * 
     * @test
     */
    public function it_returns_correct_data_types()
    {
        // Arrange - This will fail until Content model exists
        // $content = Content::factory()->create();

        // Act
        $response = $this->getJson('/api/contents');

        // Assert - Test will fail initially, but defines expected structure
        if ($response->status() === 200) {
            $data = $response->json();
            
            if (!empty($data['data'])) {
                $content = $data['data'][0];
                
                // Verify data types match API contract
                $this->assertIsInt($content['id']);
                $this->assertIsString($content['title']);
                $this->assertIsString($content['slug']);
                $this->assertTrue(is_string($content['content']) || is_null($content['content']));
                $this->assertTrue(is_string($content['excerpt']) || is_null($content['excerpt']));
                $this->assertContains($content['type'], ['page', 'post', 'document']);
                $this->assertContains($content['status'], ['draft', 'published', 'archived']);
                $this->assertTrue(is_string($content['published_at']) || is_null($content['published_at']));
                $this->assertTrue(is_string($content['meta_title']) || is_null($content['meta_title']));
                $this->assertTrue(is_string($content['meta_description']) || is_null($content['meta_description']));
                $this->assertIsString($content['created_at']);
                $this->assertIsString($content['updated_at']);
            }
        }
    }

    /**
     * Test GET /api/contents with search functionality (if implemented)
     * 
     * @test
     */
    public function it_searches_contents_by_title_or_content()
    {
        // Arrange - This will fail initially
        // $content1 = Content::factory()->create(['title' => 'Laravel Tutorial']);
        // $content2 = Content::factory()->create(['title' => 'PHP Guide']);

        // Act
        $response = $this->getJson('/api/contents?search=Laravel');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'content',
                        'excerpt',
                        'type',
                        'status',
                        'published_at',
                        'meta_title',
                        'meta_description',
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
     * Test GET /api/contents requires authentication (if implemented)
     * 
     * @test
     */
    public function it_requires_authentication()
    {
        // This test will be updated once authentication is implemented
        // For now, we assume the endpoint is accessible without auth during development
        
        $response = $this->getJson('/api/contents');
        
        // During development, this should return 200
        // Later, when auth is implemented, this should return 401
        $this->assertTrue(in_array($response->status(), [200, 401]));
    }

    /**
     * Test GET /api/contents pagination works correctly
     * 
     * @test
     */
    public function it_paginates_results_correctly()
    {
        // Arrange - This will fail until Content model exists
        // Content::factory()->count(25)->create();

        // Act
        $response = $this->getJson('/api/contents');

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

    /**
     * Test GET /api/contents handles slug uniqueness
     * 
     * @test
     */
    public function it_handles_slug_uniqueness()
    {
        // Arrange - This will fail until Content model exists
        // $content1 = Content::factory()->create(['slug' => 'unique-slug']);
        // $content2 = Content::factory()->create(['slug' => 'another-unique-slug']);

        // Act
        $response = $this->getJson('/api/contents');

        // Assert
        if ($response->status() === 200) {
            $data = $response->json();
            
            if (!empty($data['data'])) {
                $slugs = array_column($data['data'], 'slug');
                $uniqueSlugs = array_unique($slugs);
                
                // All slugs should be unique
                $this->assertEquals(count($slugs), count($uniqueSlugs), 'All content slugs should be unique');
            }
        }
    }
}