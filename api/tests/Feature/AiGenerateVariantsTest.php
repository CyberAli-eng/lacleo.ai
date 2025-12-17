<?php

use Illuminate\Support\Str;

it('generates title and location filters for Berlin, Germany', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'context' => 'contact',
        'query' => 'find senior backend engineers in Berlin, Germany',
    ]);
    $resp->assertOk();
    $filters = (array) $resp->json('filters');
    $fields = collect($filters)->pluck('field')->all();
    $hasRole = in_array('title', $fields, true) || in_array('department', $fields, true);
    expect($hasRole)->toBeTrue();
    expect($fields)->toContain('location.country');
});

it('extracts company domains and role signals for multiple companies', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'context' => 'contact',
        'query' => 'search for marketing managers at acme.com and beta.io',
    ]);
    $resp->assertOk();
    $filters = collect((array) $resp->json('filters'));
    $fields = $filters->pluck('field')->all();
    expect($fields)->toContain('company.domain');
    $hasRoleSignal = in_array('title', $fields, true) || in_array('department', $fields, true);
    expect($hasRoleSignal)->toBeTrue();
    $domains = $filters->where('field', 'company.domain')->pluck('value')->map(fn ($v) => Str::lower((string) $v))->values()->all();
    expect($domains)->toContain('acme.com');
    expect(count($domains))->toBeGreaterThan(0);
});

it('handles negative phrasing without producing invalid fields', function () {
    $resp = $this->postJson('/api/v1/ai/generate-filters', [
        'mode' => 'generate_filters',
        'context' => 'contact',
        'query' => 'exclude interns',
    ]);
    $resp->assertOk();
    $filters = (array) $resp->json('filters');
    $allowed = [
        'company', 'company.domain', 'company.industry', 'company.country',
        'title', 'seniority', 'department',
        'location.country', 'state',
    ];
    foreach ($filters as $f) {
        expect(in_array((string) ($f['field'] ?? ''), $allowed, true))->toBeTrue();
    }
});
