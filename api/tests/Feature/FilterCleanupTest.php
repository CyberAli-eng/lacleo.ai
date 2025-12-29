<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilterCleanupTest extends TestCase
{
    public function test_get_filters_endpoint_returns_correct_structure()
    {
        $response = $this->getJson('/api/v1/filters');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'group_name',
                    'filters' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'input_type',
                        ]
                    ]
                ]
            ]
        ]);

        // Verify a known filter exists
        $data = $response->json('data');
        $found = false;
        foreach ($data as $group) {
            foreach ($group['filters'] as $filter) {
                if ($filter['id'] === 'company_name') {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($found, 'company_name filter should exist');
    }
}
