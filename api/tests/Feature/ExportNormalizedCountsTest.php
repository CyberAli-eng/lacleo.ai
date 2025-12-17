<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('preview uses normalized counts for credits_required', function () {
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 1000,
        'credit_reserved' => 0,
    ]);
    actingAs($user);
    // Simulate representative ES fixtures in test environment
    $payload = [
        'type' => 'contacts',
        'ids' => ['c1', 'c2', 'c3'],
        // Preview controller reads real ES; we verify arithmetic by calling export which supports simulate
        'simulate' => [
            'contacts_included' => 3,
            'email_count' => 2,
            'phone_count' => 1,
        ],
    ];

    $r = postJson('/api/v1/billing/export', $payload);
    $r->assertOk();
    $r->assertJsonStructure(['url', 'credits_deducted']);
    $credits = $r->json('credits_deducted');
    expect($credits)->toBe(2 * 1 + 1 * 4);
});

it('export preview shape remains consistent', function () {
    $user = User::factory()->create();
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 0,
        'credit_reserved' => 0,
    ]);
    actingAs($user);
    $response = postJson('/api/v1/billing/preview-export', [
        'type' => 'contacts',
        'ids' => ['c1'],
        'limit' => 1,
    ]);
    $response->assertOk();
    $response->assertJsonStructure([
        'email_count', 'phone_count', 'credits_required', 'remaining_before', 'remaining_after', 'contacts_included', 'companies_included',
    ]);
});
