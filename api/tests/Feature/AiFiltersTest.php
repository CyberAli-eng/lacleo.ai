<?php

it('returns 422 on empty prompt and query', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', []);
    $resp->assertStatus(422);
    $resp->assertJson(['error' => 'missing_input']);
});

it('extracts title and country from text', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'query' => 'find CTO in India',
    ]);
    $resp->assertOk();
    $filters = $resp->json('filters');
    $fields = collect($filters)->pluck('field')->all();
    expect($fields)->toContain('title');
    expect($fields)->toContain('location.country');
    $title = collect($filters)->firstWhere('field', 'title');
    $country = collect($filters)->firstWhere('field', 'location.country');
    expect($title['value'])->toBe('cto');
    expect($country['value'])->toBe('india');
});

it('modifies existing filters by replacing values and adding new', function () {
    $existing = [['field' => 'title', 'operator' => '=', 'value' => 'engineer']];
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'modify_filters',
        'current_filters' => $existing,
        'instruction' => 'make it CTO and country India',
    ]);
    $resp->assertOk();
    $filters = $resp->json('filters');
    $title = collect($filters)->firstWhere('field', 'title');
    $country = collect($filters)->firstWhere('field', 'location.country');
    expect($title['value'])->toBe('cto');
    expect($country['value'])->toBe('india');
});

it('extracts company domain from text', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'query' => 'people from acme.com',
    ]);
    $resp->assertOk();
    $filters = $resp->json('filters');
    $domain = collect($filters)->firstWhere('field', 'company.domain');
    expect($domain['value'])->toBe('acme.com');
});

it('deduplicates repeated tokens', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'query' => 'CTO CTO India India',
    ]);
    $resp->assertOk();
    $filters = $resp->json('filters');
    $fields = collect($filters)->pluck('field')->unique()->values()->all();
    expect($fields)->toContain('title');
    expect($fields)->toContain('location.country');
    expect(count($fields))->toBe(2);
});
