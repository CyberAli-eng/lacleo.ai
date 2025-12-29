<?php

use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;

test('user endpoint returns user details', function () {
    $user = User::factory()->create();
    
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['id', 'name', 'email']]);
});

test('user endpoint creates workspace if missing', function () {
    $user = User::factory()->create();
    
    // Ensure no workspace exists initially
    expect(Workspace::where('owner_user_id', $user->id)->exists())->toBeFalse();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(200);
    
    // Check if workspace was created
    expect(Workspace::where('owner_user_id', $user->id)->exists())->toBeTrue();
});

test('unauthenticated user gets 401 json', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('billing usage endpoint returns data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/billing/usage');

    $response->assertStatus(200)
        ->assertJsonStructure(['balance', 'used', 'breakdown']);
});

test('saved filters endpoint returns data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/saved-filters');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('create saved filter works', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'name' => 'Test Filter',
        'entity_type' => 'company',
        'filters' => ['job_title' => ['CEO']]
    ];

    $response = $this->postJson('/api/v1/saved-filters', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'filters']]);
});
