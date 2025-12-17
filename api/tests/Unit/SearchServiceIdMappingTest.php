<?php

use App\Filters\FilterManager;
use App\Services\SearchService;

it('maps ES _id to id and attributes._id', function () {
    $service = new SearchService(app(FilterManager::class));

    $results = [
        'data' => [
            [
                '_id' => 'co_9876543210',
                'company' => 'Integra Micro Systems Pvt Ltd',
                'highlights' => null,
            ],
        ],
        'aggregations' => [],
        'current_page' => 1,
        'per_page' => 10,
        'total' => 1,
        'last_page' => 1,
    ];

    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('formatResults');
    $method->setAccessible(true);
    $out = $method->invoke($service, $results, 'company', []);

    expect($out['data'][0]['id'])->toBe('co_9876543210');
    expect($out['data'][0]['_id'])->toBe('co_9876543210');
    expect($out['data'][0]['attributes']['_id'])->toBe('co_9876543210');
});

