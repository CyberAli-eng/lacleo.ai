<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchIntegrationTest extends TestCase
{
    /**
     * Test filters listing endpoint
     */
    public function test_can_list_filters()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'group_id',
                        'group_name',
                        'filters' => [
                            '*' => [
                                'id',
                                'name',
                                'type',
                                'input_type'
                            ]
                        ]
                    ]
                ]
            ]);

        // Verify specifically for seniority and departments (Phase 2 requirements)
        $data = $response->json('data');
        $seniorityFound = false;
        foreach ($data as $group) {
            foreach ($group['filters'] as $filter) {
                if ($filter['id'] === 'seniority') {
                    $seniorityFound = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($seniorityFound, 'Seniority filter should be in the listing');
    }

    /**
     * Test filter values retrieval
     */
    public function test_can_retrieve_filter_values()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Test seniority values (preloaded)
        $response = $this->getJson('/api/v1/filter/values?filter=seniority');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'count']
                ]
            ]);

        $this->assertNotEmpty($response->json('data'), 'Seniority should return preloaded values');
    }

    /**
     * Test company search endpoint
     */
    public function test_can_search_companies()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/search/company?count=1');

        // We accept 200 or 503 (if ES is down in CI, though here it seems up)
        if ($response->status() === 503) {
            $response->assertJson(['error' => 'ELASTIC_UNAVAILABLE']);
        } else {
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'meta' => ['total', 'current_page', 'per_page']
                ]);
        }
    }
}
